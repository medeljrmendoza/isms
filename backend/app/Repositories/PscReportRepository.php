<?php

namespace App\Repositories;

use App\Models\Nonconformity;
use App\Models\PscMouAuthority;
use App\Models\PscReport;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PscReportRepository
{
    /** Same shape/caveats as AuditReportRepository — see its docblock. */
    private const COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
        ['key' => 'obs', 'label' => 'OBS', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/Psc.php's
     * loadData(). "obs" is dropped: the Observations module doesn't
     * exist in this app (see conversation notes on the standing
     * decision), so that column would always read "—".
     */
    private const MODULE_COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'date', 'label' => 'DATE OF INSPECTION', 'sortable' => true],
        ['key' => 'mou', 'label' => 'MOU', 'sortable' => false],
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
     * Ported from Controllers/Dashboard_psc.php's loadData(). Same
     * Nonconformities-only simplification as AuditReportRepository.
     */
    public function pendingQuery(): Builder
    {
        return PscReport::query()
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
                $q->where('ref_no', 'like', $term)
                    ->orWhere('dateof_inspection', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sortMap = ['date' => 'dateof_inspection'];
        $sort = in_array($query->sort, $sortable, true) ? ($sortMap[$query->sort] ?? $query->sort) : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from Controllers/Psc.php's loadData(). The
     * `WHERE vesid IN (SELECT ... tb_user_vessel)` scoping is dropped
     * like everywhere else; the vessel filter is kept since it's a
     * genuine user-facing filter (legacy's optional vesID URL segment).
     */
    public function fullTable(TableQuery $query, ?string $vesselId): LengthAwarePaginator
    {
        $builder = PscReport::query()->with(['vessel', 'mou'])
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
                $q->where('ref_no', 'like', $term)
                    ->orWhere('placeof_inspection', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sortMap = ['date' => 'dateof_inspection'];
        $sort = in_array($query->sort, $sortable, true) ? ($sortMap[$query->sort] ?? $query->sort) : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /** Ported from add_psc_report()'s insert branch. */
    public function create(array $data): PscReport
    {
        $data = $this->clearInapplicableFields($data);

        return PscReport::create([
            ...$data,
            'is_deleted' => false,
        ]);
    }

    /**
     * Ported from add_psc_report()'s update branch: when ref_no changes,
     * cascades the new value into any Nonconformity rows linked by the
     * old ref_no (loose string-key relation — see PscReport::nonconformities()),
     * so those NCs don't get orphaned.
     */
    public function update(PscReport $report, array $data): PscReport
    {
        $data = $this->clearInapplicableFields($data);

        $oldRefNo = $report->ref_no;
        unset($data['vessel_id']);

        $report->update($data);

        if ($data['ref_no'] !== $oldRefNo) {
            Nonconformity::where('source_of_nc_ref_no', $oldRefNo)->update(['source_of_nc_ref_no' => $data['ref_no']]);
        }

        return $report;
    }

    /** Ported from reopen_psc_report(): simple clear, no other side effects. */
    public function reopen(PscReport $report): PscReport
    {
        $report->update(['closing_date' => null]);

        return $report;
    }

    /**
     * Ported from delete_psc_report(): soft delete, plus cascades
     * deactivating any Nonconformity rows linked by this report's ref_no.
     */
    public function delete(PscReport $report): void
    {
        $report->update(['is_deleted' => true]);

        Nonconformity::where('source_of_nc_ref_no', $report->ref_no)->update(['is_inactive' => true]);
    }

    /** Clears detained/released fields that don't apply given the current flags. */
    private function clearInapplicableFields(array $data): array
    {
        if (empty($data['is_detained'])) {
            $data['detained_date'] = null;
            $data['detained_time'] = null;
            $data['is_released'] = false;
            $data['released_date'] = null;
            $data['released_time'] = null;
        } elseif (empty($data['is_released'])) {
            $data['released_date'] = null;
            $data['released_time'] = null;
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
    public function mouOptions(): array
    {
        return PscMouAuthority::query()->orderBy('name')->get()
            ->map(fn (PscMouAuthority $m) => ['id' => $m->id, 'label' => $m->name])
            ->all();
    }
}
