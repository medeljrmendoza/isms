<?php

namespace App\Repositories\NonSire;

use App\Models\NonSire\NonSireReport;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class NonSireReportRepository
{
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => true],
        ['key' => 'pending', 'label' => 'PENDING', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/Non_sire.php's
     * loadData(). "pending" (Observations) is dropped: that module
     * doesn't exist in this app. Like SIRE, there's no ref_no and so no
     * "NC" column either.
     */
    private const MODULE_COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => true],
        ['key' => 'company_name', 'label' => 'COMPANY', 'sortable' => true],
        ['key' => 'inspector_name', 'label' => 'INSPECTOR', 'sortable' => true],
        ['key' => 'inspection_type', 'label' => 'INSPECTION TYPE', 'sortable' => true],
        ['key' => 'pass_fail', 'label' => 'PASS/FAIL', 'sortable' => true],
        ['key' => 'published', 'label' => 'PUBLISHED', 'sortable' => false],
        ['key' => 'is_approved', 'label' => 'APPROVED', 'sortable' => false],
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
     * Ported from Controllers/Dashboard_non_sire.php's loadData() —
     * same shape and same Observations-half-of-the-filter gap as
     * SireReportRepository (see its docblock).
     */
    public function pendingQuery(): Builder
    {
        return NonSireReport::query()
            ->with('vessel')
            ->where('is_deleted', false)
            ->where('is_published', true)
            ->where('is_approved', false);
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('dateof_inspection', 'like', $term)
                    ->orWhere('placeof_inspection', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from Controllers/Non_sire.php's loadData(). The
     * `WHERE vesID IN (SELECT ... tb_user_vessel)` scoping is dropped
     * like everywhere else; the vessel filter is kept since it's a
     * genuine user-facing filter.
     */
    public function fullTable(TableQuery $query, ?string $vesselId): LengthAwarePaginator
    {
        $builder = NonSireReport::query()->with('vessel')->where('is_deleted', false);

        if ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('vessel_id', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('dateof_inspection', 'like', $term)
                    ->orWhere('placeof_inspection', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhere('inspector_name', 'like', $term)
                    ->orWhere('inspection_type', 'like', $term)
                    ->orWhere('pass_fail', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from add_non_sire_report()'s insert branch: new records are
     * always SHORE-added (there's no VESSEL-origin path reachable from
     * this admin) and start unpublished/unapproved.
     */
    public function create(array $data): NonSireReport
    {
        return NonSireReport::create([
            ...$data,
            'added_by' => 'SHORE',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);
    }

    /**
     * Ported from add_non_sire_report()'s edit branch. Vessel and
     * added_by are frozen at creation time (legacy always re-reads them
     * from the existing row). is_published is left untouched — only the
     * separate publish toggle changes it — but is_approved
     * unconditionally resets to false on every save, published or not
     * (legacy hardcodes `"is_approved" => "0"` regardless of branch,
     * same as SIRE/External Audits). There's no ref_no here at all, so
     * there's nothing to cascade into Nonconformities.
     */
    public function update(NonSireReport $report, array $data): NonSireReport
    {
        unset($data['vessel_id']);

        $report->update([...$data, 'is_approved' => false]);

        return $report;
    }

    /** Ported from publish_non_sire_report(): toggles is_published, always sets is_approved true. */
    public function publish(NonSireReport $report): NonSireReport
    {
        $report->update([
            'is_published' => ! $report->is_published,
            'is_approved' => true,
        ]);

        return $report;
    }

    /** Ported from approve_non_sire_report(). */
    public function approve(NonSireReport $report): NonSireReport
    {
        $report->update(['is_approved' => true]);

        return $report;
    }

    /**
     * Ported from delete_non_sire_report(): a plain soft delete. No
     * Nonconformity cascade — Non-SIRE has no ref_no to link one by.
     */
    public function delete(NonSireReport $report): void
    {
        $report->update(['is_deleted' => true]);
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }
}
