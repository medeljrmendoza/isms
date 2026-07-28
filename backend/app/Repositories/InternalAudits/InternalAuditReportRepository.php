<?php

namespace App\Repositories\InternalAudits;

use App\Models\InternalAudits\InternalAuditReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class InternalAuditReportRepository
{
    /** Same shape/caveats as AuditReportRepository — see its docblock. */
    private const COLUMNS = [
        ['key' => 'audit_ref', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'this_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
        ['key' => 'obs', 'label' => 'OBS', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/Internal.php's
     * loadData(). "obs" is dropped: the Observations module doesn't
     * exist in this app, so that column would always read "—".
     */
    private const MODULE_COLUMNS = [
        ['key' => 'audit_ref', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'this_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_audit', 'label' => 'PORT OF AUDIT', 'sortable' => true],
        ['key' => 'typeof_audit', 'label' => 'TYPE', 'sortable' => true],
        ['key' => 'auditor_name', 'label' => 'AUDITOR', 'sortable' => true],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    public static function moduleColumns(): array
    {
        return self::MODULE_COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_internal_audit_reports.php's
     * loadData(). Same Nonconformities-only simplification as
     * AuditReportRepository — see its docblock for why Observations
     * isn't part of this filter yet.
     */
    public function pendingQuery(): Builder
    {
        return InternalAuditReport::query()
            ->with('vessel')
            ->where('is_deleted', false)
            ->whereHas('nonconformities', function (Builder $q) {
                $q->where('is_inactive', false)->whereNull('close_out_date');
            })
            ->withCount([
                'nonconformities as pending_nc_count' => fn (Builder $q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
                'nonconformities as total_nc_count' => fn (Builder $q) => $q->where('is_inactive', false),
            ]);
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('audit_ref', 'like', $term)
                    ->orWhere('this_date', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'this_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from Controllers/Internal.php's loadData(). The
     * `WHERE vesID IN (SELECT ... tb_user_vessel)` scoping is dropped
     * like everywhere else; the vessel filter is kept since it's a
     * genuine user-facing filter. Unlike Company Inspections, there's no
     * "COMPANY" sentinel — internal audits are always vessel-specific.
     */
    public function fullTable(TableQuery $query, ?string $vesselId): LengthAwarePaginator
    {
        $builder = InternalAuditReport::query()->with('vessel')
            ->where('is_deleted', false)
            ->withCount([
                'nonconformities as pending_nc_count' => fn (Builder $q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
                'nonconformities as total_nc_count' => fn (Builder $q) => $q->where('is_inactive', false),
            ]);

        if ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('vessel_id', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('audit_ref', 'like', $term)
                    ->orWhere('this_date', 'like', $term)
                    ->orWhere('placeof_audit', 'like', $term)
                    ->orWhere('typeof_audit', 'like', $term)
                    ->orWhere('auditor_name', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'this_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /** Ported from add_internal_report()'s insert branch. */
    public function create(array $data): InternalAuditReport
    {
        return InternalAuditReport::create([
            ...$data,
            'is_deleted' => false,
        ]);
    }

    /**
     * Ported from add_internal_report()'s update branch. Vessel is
     * frozen at creation time (legacy always re-reads it from the
     * existing row). When audit_ref changes, the new value cascades
     * into any Nonconformity rows linked by the old ref (loose
     * string-key relation — see InternalAuditReport::nonconformities()),
     * so those NCs don't get orphaned.
     */
    public function update(InternalAuditReport $report, array $data): InternalAuditReport
    {
        $oldRef = $report->audit_ref;

        unset($data['vessel_id']);

        $report->update($data);

        if ($data['audit_ref'] !== $oldRef) {
            Nonconformity::where('source_of_nc_ref_no', $oldRef)->update(['source_of_nc_ref_no' => $data['audit_ref']]);
        }

        return $report;
    }

    /**
     * Ported from delete_internal_report(): soft delete, plus cascades
     * deactivating any Nonconformity rows linked by this report's ref.
     */
    public function delete(InternalAuditReport $report): void
    {
        $report->update(['is_deleted' => true]);

        Nonconformity::where('source_of_nc_ref_no', $report->audit_ref)->update(['is_inactive' => true]);
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }
}
