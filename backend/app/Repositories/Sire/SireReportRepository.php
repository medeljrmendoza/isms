<?php

namespace App\Repositories\Sire;

use App\Models\Sire\SireReport;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class SireReportRepository
{
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => true],
        ['key' => 'pending', 'label' => 'PENDING', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/Sire.php's
     * loadData(). "pending" (Observations) is dropped: that module
     * doesn't exist in this app. Unlike every other audit-style report
     * module, there's no "NC" column either — SIRE has no ref_no, so it
     * never links to Nonconformities at all.
     */
    private const MODULE_COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => true],
        ['key' => 'company_name', 'label' => 'COMPANY', 'sortable' => true],
        ['key' => 'inspector_name', 'label' => 'INSPECTOR', 'sortable' => true],
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
     * Ported from Controllers/Dashboard_sire.php's loadData() WHERE
     * clause — with a real reduction in what "pending" means here, not
     * just a missing display column: legacy shows a report if it's
     * published-and-unapproved OR has pending observations. Observations
     * doesn't exist as a module yet (same deferral as the audit-style
     * dashlets), so only the first half applies. A SIRE report with
     * open observations but that's already approved won't appear here
     * yet — unlike the audit dashlets, this isn't a minor gap, it's half
     * of this filter's real meaning.
     */
    public function pendingQuery(): Builder
    {
        return SireReport::query()
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
     * Ported from Controllers/Sire.php's loadData(). The
     * `WHERE vesID IN (SELECT ... tb_user_vessel)` scoping is dropped
     * like everywhere else; the vessel filter is kept since it's a
     * genuine user-facing filter.
     */
    public function fullTable(TableQuery $query, ?string $vesselId): LengthAwarePaginator
    {
        $builder = SireReport::query()->with('vessel')->where('is_deleted', false);

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
     * Ported from add_sire_report()'s insert branch: new records are
     * always SHORE-added (there's no VESSEL-origin path reachable from
     * this admin) and start unpublished/unapproved.
     */
    public function create(array $data): SireReport
    {
        return SireReport::create([
            ...$data,
            'added_by' => 'SHORE',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);
    }

    /**
     * Ported from add_sire_report()'s edit branch. Vessel and added_by
     * are frozen at creation time (legacy always re-reads them from the
     * existing row). is_published is left untouched — only the separate
     * publish toggle changes it — but is_approved unconditionally
     * resets to false on every save, published or not (legacy hardcodes
     * `"is_approved" => "0"` regardless of branch, same as External
     * Audits). There's no ref_no here at all, so — unlike every other
     * report module — there's nothing to cascade into Nonconformities.
     */
    public function update(SireReport $report, array $data): SireReport
    {
        unset($data['vessel_id']);

        $report->update([...$data, 'is_approved' => false]);

        return $report;
    }

    /** Ported from publish_sire_report(): toggles is_published, always sets is_approved true. */
    public function publish(SireReport $report): SireReport
    {
        $report->update([
            'is_published' => ! $report->is_published,
            'is_approved' => true,
        ]);

        return $report;
    }

    /** Ported from approve_sire_report(). */
    public function approve(SireReport $report): SireReport
    {
        $report->update(['is_approved' => true]);

        return $report;
    }

    /**
     * Ported from delete_sire_report(): a plain soft delete. No
     * Nonconformity cascade — SIRE has no ref_no to link one by.
     */
    public function delete(SireReport $report): void
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
