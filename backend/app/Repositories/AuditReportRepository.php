<?php

namespace App\Repositories;

use App\Models\AuditKind;
use App\Models\AuditReport;
use App\Models\AuditType;
use App\Models\Nonconformity;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditReportRepository
{
    /**
     * "vessel_company" (resolved vessel/company name) and "nc" (a
     * computed "pending/total" string) aren't real sortable columns.
     * "obs" isn't sortable either — see class docblock on why it's not
     * even a real count yet.
     */
    private const COLUMNS = [
        ['key' => 'audit_ref', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel_company', 'label' => 'VESSEL/COMPANY', 'sortable' => false],
        ['key' => 'this_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
        ['key' => 'obs', 'label' => 'OBS', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/Company.php's
     * loadData(). "obs" is dropped: the Observations module doesn't
     * exist in this app, so that column would always read "—".
     */
    private const MODULE_COLUMNS = [
        ['key' => 'audit_ref', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel_company', 'label' => 'VESSEL/COMPANY', 'sortable' => false],
        ['key' => 'this_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_audit', 'label' => 'PORT OF INSPECTION', 'sortable' => true],
        ['key' => 'audit_type', 'label' => 'TYPE', 'sortable' => false],
        ['key' => 'audit_kind', 'label' => 'KIND', 'sortable' => false],
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
     * Ported from Controllers/Dashboard_company_inspections.php's
     * loadData(): audit reports that aren't deleted and have at least
     * one pending (open, active) non-conformity attributed to them.
     *
     * Two deliberate gaps vs. legacy, both agreed on in conversation:
     * - The legacy filter also shows a report if it has pending
     *   *Observations* — that module doesn't exist yet, so this only
     *   checks Nonconformities. A report with pending observations but
     *   zero pending NCs won't appear here yet.
     * - Legacy's COMPANY-scoped branch additionally required
     *   user_level != 'MEMBER'; we don't have roles yet, so company-wide
     *   reports are visible to everyone, consistent with vessel scoping
     *   being deferred the same way elsewhere.
     */
    public function pendingQuery(): Builder
    {
        return AuditReport::query()
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
                    ->orWhere('company', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'this_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from Controllers/Company.php's loadData(). The
     * `WHERE vesID IN (SELECT ... tb_user_vessel)` scoping is dropped
     * like everywhere else; the vessel filter is kept since it's a
     * genuine user-facing filter. Legacy's sentinel vesID of "NA" means
     * "company-wide reports only" — kept here as $vesselId === 'COMPANY'.
     */
    public function fullTable(TableQuery $query, ?string $vesselId): LengthAwarePaginator
    {
        $builder = AuditReport::query()->with(['vessel', 'auditType', 'auditKind'])
            ->where('is_deleted', false)
            ->withCount([
                'nonconformities as pending_nc_count' => fn (Builder $q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
                'nonconformities as total_nc_count' => fn (Builder $q) => $q->where('is_inactive', false),
            ]);

        if ($vesselId === 'COMPANY') {
            $builder->where('vessel_company', 'COMPANY');
        } elseif ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('vessel_id', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('audit_ref', 'like', $term)
                    ->orWhere('this_date', 'like', $term)
                    ->orWhere('placeof_audit', 'like', $term)
                    ->orWhere('company', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term))
                    ->orWhereHas('auditType', fn (Builder $t) => $t->where('name', 'like', $term))
                    ->orWhereHas('auditKind', fn (Builder $k) => $k->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'this_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /** Ported from add_company_report()'s insert branch. */
    public function create(array $data): AuditReport
    {
        $data = $this->applyVesselCompany($data);

        return AuditReport::create([
            ...$data,
            'is_deleted' => false,
        ]);
    }

    /**
     * Ported from add_company_report()'s update branch. vessel_company
     * and vessel_id are frozen at creation time (legacy re-reads both
     * from the existing row), but the company *name* stays editable —
     * legacy deliberately re-reads that one from the edit payload.
     *
     * When audit_ref changes, the new value cascades into any
     * Nonconformity rows linked by the old ref (loose string-key
     * relation — see AuditReport::nonconformities()) so they aren't
     * orphaned.
     */
    public function update(AuditReport $report, array $data): AuditReport
    {
        $oldRef = $report->audit_ref;

        unset($data['vessel_company'], $data['vessel_id']);

        if ($report->vessel_company === 'VESSEL') {
            unset($data['company']);
        }

        $report->update($data);

        if ($data['audit_ref'] !== $oldRef) {
            Nonconformity::where('source_of_nc_ref_no', $oldRef)->update(['source_of_nc_ref_no' => $data['audit_ref']]);
        }

        return $report;
    }

    /**
     * Ported from delete_company_report(): soft delete, plus cascades
     * deactivating any Nonconformity rows linked by this report's ref.
     */
    public function delete(AuditReport $report): void
    {
        $report->update(['is_deleted' => true]);

        Nonconformity::where('source_of_nc_ref_no', $report->audit_ref)->update(['is_inactive' => true]);
    }

    /**
     * A report is attributed to either a vessel or the company, never
     * both — legacy blanks whichever side doesn't apply.
     */
    private function applyVesselCompany(array $data): array
    {
        if (($data['vessel_company'] ?? null) === 'VESSEL') {
            $data['company'] = null;
        } else {
            $data['vessel_id'] = null;
        }

        return $data;
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function auditTypeOptions(): array
    {
        return AuditType::query()->orderBy('name')->get()
            ->map(fn (AuditType $t) => ['id' => $t->id, 'label' => $t->name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function auditKindOptions(): array
    {
        return AuditKind::query()->orderBy('name')->get()
            ->map(fn (AuditKind $k) => ['id' => $k->id, 'label' => $k->name])
            ->all();
    }
}
