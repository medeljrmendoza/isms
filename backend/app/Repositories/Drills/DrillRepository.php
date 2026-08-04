<?php

namespace App\Repositories\Drills;

use App\Models\Drills\DrillList;
use App\Models\Drills\DrillReport;
use App\Models\Drills\DrillReportCrew;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Dashboard_drill.php (the summaries()/table()
 * dashlet methods) and Controllers/Drill.php (everything else). Unlike
 * every other module, there's no create or delete here — legacy's
 * add_record() only ever edits a drill_report row that already exists
 * (drill reports originate solely from the unmigrated vessel-side app,
 * against a scheduled drill_list slot), and delete_record() doesn't
 * exist in the controller at all despite a delete button being rendered
 * for it in view_calendar_reports() — dead UI in legacy, not ported.
 */
class DrillRepository
{
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => true],
        ['key' => 'upcoming', 'label' => 'UPCOMING', 'sortable' => true],
        ['key' => 'overdue', 'label' => 'OVERDUE', 'sortable' => true],
    ];

    private const MONTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /** @return Collection<int, array{vessel: Vessel, upcoming: int, overdue: int}> */
    public function summaries(): Collection
    {
        $drillLists = DrillList::query()->where('is_active', true)->with('vessels')->get();
        $today = Carbon::today();

        return Vessel::query()->orderBy('name')->get()->map(function (Vessel $vessel) use ($drillLists, $today) {
            $upcoming = 0;
            $overdue = 0;

            foreach ($drillLists as $drillList) {
                $applies = $drillList->applies_to_all_vessels
                    || $drillList->vessels->contains('id', $vessel->id);

                if (! $applies) {
                    continue;
                }

                $lastDrillDate = $drillList->reports()
                    ->where('vessel_id', $vessel->id)
                    ->max('drill_date');

                if ($lastDrillDate === null) {
                    continue;
                }

                $nextDrill = $this->nextDrillDate($lastDrillDate, $drillList->frequency_type, $drillList->frequency_count);

                if ($nextDrill->lt($today)) {
                    $overdue++;
                } elseif ($nextDrill->copy()->subDays(30)->lte($today)) {
                    $upcoming++;
                }
            }

            return ['vessel' => $vessel, 'upcoming' => $upcoming, 'overdue' => $overdue];
        });
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $rows = $this->summaries();

        if ($query->search !== null) {
            $term = mb_strtolower($query->search);
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['vessel']->display_name), $term));
        }

        $sortable = [
            'vessel' => fn (array $row) => mb_strtolower($row['vessel']->display_name),
            'upcoming' => fn (array $row) => $row['upcoming'],
            'overdue' => fn (array $row) => $row['overdue'],
        ];
        $sortKey = $sortable[$query->sort ?? 'vessel'] ?? $sortable['vessel'];

        $sorted = $rows->sortBy($sortKey, SORT_REGULAR, $query->direction === 'desc')->values();

        $total = $sorted->count();
        $items = $sorted->slice(($query->page - 1) * $query->perPage, $query->perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $query->perPage,
            $query->page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * Ported from Controllers/Dashboard_drill.php's count_drill_overdue()/
     * count_drill_upcoming(): same vessel list scoping as
     * PmsRepository::legacyTable() and VesselDocumentationRepository::legacyTable()
     * (assigned AND active vessel AND active principal). For each
     * eligible vessel, checks every active drill list that applies to
     * it (vessel_access='ALL' or an explicit tb_drill_list_vessel row —
     * legacy doesn't filter that row's is_inactive flag, so neither does
     * this), takes the last report date (legacy's MAX(drill_date) query
     * has no is_deleted/is_approved filter either), and buckets the next
     * due date the same way as the local `summaries()`.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $eligibleVesselIds = LegacyDb::assignedVesselIds($legacyUserId)
            ->intersect(LegacyDb::activeVesselIdsWithActivePrincipal());

        $drillLists = DB::connection('legacy')->table('tb_drill_list')
            ->where('status', '1')
            ->get(['drillListID', 'vessel_access', 'frequency_type', 'frequency_count']);

        $explicitVesselIds = DB::connection('legacy')->table('tb_drill_list_vessel')
            ->whereIn('vesID', $eligibleVesselIds)
            ->get(['drillListID', 'vesID'])
            ->groupBy('vesID')
            ->map(fn ($rows) => $rows->pluck('drillListID')->all());

        $lastDrillDates = DB::connection('legacy')->table('tb_drill_report')
            ->whereIn('vesID', $eligibleVesselIds)
            ->selectRaw('vesID, drillListID, MAX(drill_date) as max_drill_date')
            ->groupBy('vesID', 'drillListID')
            ->get()
            ->groupBy('vesID');

        $today = Carbon::today();

        $rows = collect($eligibleVesselIds)->map(function ($vesID) use ($drillLists, $explicitVesselIds, $lastDrillDates, $today, $vessels) {
            $applicableLists = $drillLists->filter(fn ($list) => $list->vessel_access === 'ALL'
                || in_array($list->drillListID, $explicitVesselIds->get($vesID, []), true));

            $lastDrillByList = $lastDrillDates->get($vesID, collect())->keyBy('drillListID');

            $upcoming = 0;
            $overdue = 0;

            foreach ($applicableLists as $list) {
                $lastDrillDate = $lastDrillByList->get($list->drillListID)?->max_drill_date;

                if ($lastDrillDate === null || $lastDrillDate === '0000-00-00') {
                    continue;
                }

                $nextDrill = $this->nextDrillDate($lastDrillDate, $list->frequency_type, $list->frequency_count);

                if ($nextDrill->lt($today)) {
                    $overdue++;
                } elseif ($nextDrill->copy()->subDays(30)->lte($today)) {
                    $upcoming++;
                }
            }

            $vesselName = $vessels[$vesID] ?? '';

            return ['vessel' => $vesselName, 'upcoming' => $upcoming, 'overdue' => $overdue, '_sort_vessel' => $vesselName];
        });

        if ($query->search !== null) {
            $term = mb_strtolower($query->search);
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['vessel']), $term));
        }

        $sortMap = ['vessel' => '_sort_vessel', 'upcoming' => 'upcoming', 'overdue' => 'overdue'];
        $sortKey = $sortMap[$query->sort ?? 'vessel'] ?? '_sort_vessel';

        $sorted = $rows->sortBy($sortKey, SORT_REGULAR, $query->direction === 'desc')->values()
            ->map(fn (array $r) => collect($r)->except('_sort_vessel')->all());

        $total = $sorted->count();
        $items = $sorted->slice(($query->page - 1) * $query->perPage, $query->perPage)->values()->all();

        return [
            'rows' => $items,
            'meta' => [
                'current_page' => $query->page,
                'last_page' => (int) max(1, ceil($total / $query->perPage)),
                'per_page' => $query->perPage,
                'total' => $total,
            ],
        ];
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /**
     * Ported from get_drill_report_year(): distinct years with at least
     * one report, plus the current year so a vessel with no history yet
     * still has something selectable.
     */
    public function yearOptions(): array
    {
        $years = DrillReport::query()
            ->selectRaw("DISTINCT strftime('%Y', drill_date) as year")
            ->pluck('year')
            ->filter()
            ->map(fn ($y) => (int) $y);

        return $years->push((int) now()->year)->unique()->sortDesc()->values()->all();
    }

    /**
     * Ported from Controllers/Drill.php's loadCalendarData(). One row
     * per active drill_list applicable to the vessel (all-vessels or
     * explicitly assigned), each carrying last/next/status plus a
     * per-month bucket of that year's reports. Legacy shows nothing at
     * all without a vessel selected (`WHERE tb_drill_list.drillListID
     * = ""` when vesID is blank) — same here, vesselId is required.
     */
    public function calendarGrid(int $vesselId, int $year): Collection
    {
        $today = Carbon::today();

        return DrillList::query()
            ->where('is_active', true)
            ->where(function (Builder $q) use ($vesselId) {
                $q->where('applies_to_all_vessels', true)
                    ->orWhereHas('vessels', fn (Builder $v) => $v->where('vessels.id', $vesselId));
            })
            ->with(['reports' => fn ($q) => $q->where('vessel_id', $vesselId)->orderBy('drill_date')])
            ->orderBy('drill_type')
            ->orderBy('name')
            ->get()
            ->map(function (DrillList $drillList) use ($year, $today) {
                $reports = $drillList->reports;
                $lastDrillDate = $reports->max('drill_date');
                $nextDrill = $lastDrillDate
                    ? $this->nextDrillDate($lastDrillDate, $drillList->frequency_type, $drillList->frequency_count)
                    : null;

                $status = null;
                if ($nextDrill) {
                    if ($nextDrill->lt($today)) {
                        $status = 'overdue';
                    } elseif ($nextDrill->copy()->subDays(30)->lte($today)) {
                        $status = 'upcoming';
                    }
                }

                $yearReports = $reports->filter(fn (DrillReport $r) => $r->drill_date->year === $year);
                $months = [];
                foreach (self::MONTHS as $month) {
                    $months[$month] = $yearReports->filter(fn (DrillReport $r) => $r->drill_date->month === $month)
                        ->map(fn (DrillReport $r) => ['id' => $r->id, 'day' => $r->drill_date->day])
                        ->values()->all();
                }

                return [
                    'id' => $drillList->id,
                    'drill_type' => $drillList->drill_type,
                    'name' => $drillList->name,
                    'frequency' => $this->frequencyLabel($drillList->frequency_type, $drillList->frequency_count),
                    'last_drill' => $lastDrillDate?->format('Y-m-d'),
                    'next_drill' => $nextDrill?->toDateString(),
                    'status' => $status,
                    'months' => $months,
                ];
            });
    }

    /**
     * Ported from view_calendar_reports(): the flat list of reports
     * behind one calendar cell (one drill list, one vessel, one month).
     */
    public function reportsForCell(int $drillListId, int $vesselId, int $year, int $month): Collection
    {
        return DrillReport::query()
            ->where('drill_list_id', $drillListId)
            ->where('vessel_id', $vesselId)
            ->whereYear('drill_date', $year)
            ->whereMonth('drill_date', $month)
            ->orderBy('drill_date')
            ->get();
    }

    /**
     * Ported from add_record(): edit-only — vessel, drill_list, and
     * drill_date's report origin are all frozen (legacy always re-reads
     * vesID/drillListID from the existing row and never lets shore set
     * them). Crew rows are fully replaced on every save, same
     * delete-then-recreate pattern as every other sub-table in this app.
     *
     * @param  array<int, array{crew_name:string}>  $crew
     */
    public function update(DrillReport $report, array $data, array $crew): DrillReport
    {
        $report->update($data);

        $report->crew()->delete();
        foreach (array_values($crew) as $index => $row) {
            DrillReportCrew::create([
                'drill_report_id' => $report->id,
                'crew_name' => $row['crew_name'],
                'arrangement' => $index,
            ]);
        }

        return $report;
    }

    private function nextDrillDate(string $lastDrillDate, string $frequencyType, int $frequencyCount): Carbon
    {
        return Carbon::parse($lastDrillDate)->add(match ($frequencyType) {
            'D' => "{$frequencyCount} days",
            'W' => "{$frequencyCount} weeks",
            'M' => "{$frequencyCount} months",
            'Y' => "{$frequencyCount} years",
        });
    }

    private function frequencyLabel(string $frequencyType, int $frequencyCount): string
    {
        $unit = match ($frequencyType) {
            'D' => 'Day',
            'W' => 'Week',
            'M' => 'Month',
            'Y' => 'Year',
        };

        return "{$frequencyCount} {$unit}(s)";
    }
}
