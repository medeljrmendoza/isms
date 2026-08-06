<?php

namespace App\Repositories\Drills;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Dashboard_drill.php (the dashlet methods) and
 * Controllers/Drill.php (everything else). Unlike every other module,
 * there's no create or delete here — legacy's add_record() only ever
 * edits a drill_report row that already exists (drill reports originate
 * solely from the unmigrated vessel-side app, against a scheduled
 * drill_list slot), and delete_record() doesn't exist in the controller
 * at all despite a delete button being rendered for it in
 * view_calendar_reports() — dead UI in legacy, not ported. Edit itself
 * is also unreachable here: legacy never lets shore edit a drill
 * report (can_edit is always false), so this module is entirely
 * read-only.
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
     * due date.
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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /** Distinct years with at least one report, plus the current year, reading tb_drill_report directly from the legacy connection. */
    public function legacyYearOptions(): array
    {
        $years = DB::connection('legacy')->table('tb_drill_report')
            ->where('drill_date', '!=', '0000-00-00')
            ->selectRaw('DISTINCT YEAR(drill_date) as year')
            ->pluck('year')
            ->filter()
            ->map(fn ($y) => (int) $y);

        return $years->push((int) now()->year)->unique()->sortDesc()->values()->all();
    }

    /**
     * Ported from Controllers/Drill.php's loadCalendarData(), reading
     * tb_drill_list/tb_drill_report directly from the legacy connection.
     * One row per active drill_list applicable to the vessel (all-vessels
     * or explicitly assigned), each carrying last/next/status plus a
     * per-month bucket of that year's reports. Keeps the vesID-in-
     * assigned-vessels scoping in addition to matching the exact vessel
     * requested. Legacy shows nothing at all without a vessel selected —
     * same here, vesselId is required.
     */
    public function legacyCalendarGrid(string $vesselId, int $year, ?string $legacyUserId): Collection
    {
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        if (! $assignedVesselIds->contains($vesselId)) {
            return collect();
        }

        $today = Carbon::today();

        $explicitListIds = DB::connection('legacy')->table('tb_drill_list_vessel')
            ->where('vesID', $vesselId)
            ->pluck('drillListID');

        $drillLists = DB::connection('legacy')->table('tb_drill_list')
            ->leftJoin('tb_drill_type', 'tb_drill_type.drillTypeID', '=', 'tb_drill_list.drillTypeID')
            ->where('tb_drill_list.status', '1')
            ->where(function ($q) use ($explicitListIds) {
                $q->where('tb_drill_list.vessel_access', 'ALL')
                    ->orWhereIn('tb_drill_list.drillListID', $explicitListIds);
            })
            ->orderBy('tb_drill_list.arrangement')
            ->get(['tb_drill_list.drillListID', 'tb_drill_list.drill_name', 'tb_drill_list.frequency_type', 'tb_drill_list.frequency_count', 'tb_drill_type.drill_type_name']);

        $reports = DB::connection('legacy')->table('tb_drill_report')
            ->where('vesID', $vesselId)
            ->whereIn('drillListID', $drillLists->pluck('drillListID'))
            ->where('is_deleted', '!=', '1')
            ->orderBy('drill_date')
            ->get(['drillID', 'drillListID', 'drill_date'])
            ->groupBy('drillListID');

        return $drillLists->map(function ($list) use ($reports, $year, $today) {
            $listReports = $reports->get($list->drillListID, collect());
            $lastDrillDate = $listReports->max('drill_date');
            $nextDrill = ($lastDrillDate !== null && $lastDrillDate !== '0000-00-00')
                ? $this->nextDrillDate($lastDrillDate, $list->frequency_type, $list->frequency_count)
                : null;

            $status = null;
            if ($nextDrill) {
                if ($nextDrill->lt($today)) {
                    $status = 'overdue';
                } elseif ($nextDrill->copy()->subDays(30)->lte($today)) {
                    $status = 'upcoming';
                }
            }

            $yearReports = $listReports->filter(fn ($r) => $r->drill_date !== '0000-00-00' && Carbon::parse($r->drill_date)->year === $year);
            $months = [];
            foreach (self::MONTHS as $month) {
                $months[$month] = $yearReports->filter(fn ($r) => Carbon::parse($r->drill_date)->month === $month)
                    ->map(fn ($r) => ['id' => $r->drillID, 'day' => Carbon::parse($r->drill_date)->day])
                    ->values()->all();
            }

            return [
                'id' => $list->drillListID,
                'drill_type' => $list->drill_type_name,
                'name' => $list->drill_name,
                'frequency' => $this->frequencyLabel($list->frequency_type, $list->frequency_count),
                'last_drill' => ($lastDrillDate === null || $lastDrillDate === '0000-00-00') ? null : $lastDrillDate,
                'next_drill' => $nextDrill?->toDateString(),
                'status' => $status,
                'months' => $months,
            ];
        });
    }

    /**
     * Ported from view_calendar_reports(): the flat list of reports
     * behind one calendar cell (one drill list, one vessel, one month),
     * reading tb_drill_report directly from the legacy connection.
     */
    public function legacyReportsForCell(string $drillListId, string $vesselId, int $year, int $month): Collection
    {
        return DB::connection('legacy')->table('tb_drill_report')
            ->where('drillListID', $drillListId)
            ->where('vesID', $vesselId)
            ->where('is_deleted', '!=', '1')
            ->whereRaw('YEAR(drill_date) = ?', [$year])
            ->whereRaw('MONTH(drill_date) = ?', [$month])
            ->orderBy('drill_date')
            ->get(['drillID', 'drill_date', 'drill_position', 'drill_time_from']);
    }

    /**
     * Same as show()'s mapDetail(), reading tb_drill_report directly
     * from the legacy connection. Read-only — no edit action exists for
     * legacy-sourced reports.
     */
    public function legacyDetail(string $drillID): ?array
    {
        $r = DB::connection('legacy')->table('tb_drill_report')
            ->leftJoin('tb_drill_list', 'tb_drill_list.drillListID', '=', 'tb_drill_report.drillListID')
            ->leftJoin('tb_drill_type', 'tb_drill_type.drillTypeID', '=', 'tb_drill_list.drillTypeID')
            ->where('tb_drill_report.drillID', $drillID)
            ->select([
                'tb_drill_report.*',
                'tb_drill_list.drill_name', 'tb_drill_type.drill_type_name',
            ])
            ->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();
        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;

        $crew = DB::connection('legacy')->table('tb_drill_report_crew')
            ->where('drillID', $drillID)
            ->where('is_inactive', '!=', '1')
            ->orderBy('arrangement')
            ->pluck('crew_name')
            ->map(fn ($name) => ['crew_name' => $name])
            ->all();

        return [
            'id' => $r->drillID,
            'vessel' => $vessels[$r->vesID] ?? '',
            'drill_list_id' => $r->drillListID,
            'drill_name' => $r->drill_name ?? '',
            'drill_type' => $r->drill_type_name,
            'master_name' => $r->master_name,
            'drill_date' => $zeroDateToNull($r->drill_date),
            'drill_time_from' => $r->drill_time_from,
            'drill_position' => $r->drill_position,
            'drill_details' => $r->drill_details,
            'drill_deficiencies' => $r->drill_deficiencies,
            'drill_corrective_action' => $r->drill_corrective_action,
            'report_date' => $zeroDateToNull($r->report_date),
            'vessel_remarks' => $r->vessel_remarks,
            'receipt_date' => $zeroDateToNull($r->receipt_date),
            'shore_remarks' => $r->shore_remarks,
            'can_edit' => false,
            'crew' => $crew,
        ];
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
