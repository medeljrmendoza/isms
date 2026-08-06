<?php

namespace App\Repositories\Pms;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Dashboard_pms.php. Same vessel-summary-grid
 * shape as DrillRepository — see its docblock for why this is a table
 * of one row per vessel instead of a raw activity list, and why the
 * drill-down summary pages aren't ported.
 *
 * Legacy schedules each activity one of two ways: by due_date, or by
 * accumulated running hours (only for activities with an entry in
 * tb_pms_running_hours — modeled here as `since_delivery` being
 * non-null). The unit-to-hours conversion and the upcoming/overdue
 * interval selection (upcoming prefers min_count_interval, overdue
 * prefers max_count_interval) are both ported as-is.
 */
class PmsRepository
{
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => true],
        ['key' => 'upcoming', 'label' => 'UPCOMING ACTIVITIES', 'sortable' => true],
        ['key' => 'overdue', 'label' => 'OVERDUE ACTIVITIES', 'sortable' => true],
        ['key' => 'postponed', 'label' => 'POSTPONED ACTIVITIES', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    private const HOURS_PER_UNIT = ['H' => 1, 'D' => 24, 'W' => 7 * 24, 'M' => 30 * 24, 'Y' => 365 * 24];

    /**
     * Reads the dashlet's per-vessel counts from the real legacy staging
     * database — ported directly from Controllers/Dashboard_pms.php's
     * count_pms_upcoming()/count_pms_overdue()/count_pms_postpone(), which
     * run as three independent queries rather than one combined pass. Two
     * quirks preserved as-is: (1) activities without a running-hours
     * meter are counted toward upcoming/overdue regardless of
     * is_postpone — only RH-tracked activities skip both when postponed;
     * (2) postponed is counted separately across all active activities
     * for the vessel, so a non-RH activity can be both postponed and
     * overdue/upcoming. Also ported: pms_vessel_query's own scoping —
     * only vessels that are vessel_status='ACTIVE', belong to a principal
     * with status='1', and are assigned to the logged-in user appear as
     * rows at all.
     */
    public function legacySummaries(?string $legacyUserId): Collection
    {
        $eligibleVesselIds = LegacyDb::assignedVesselIds($legacyUserId)
            ->intersect(LegacyDb::activeVesselIdsWithActivePrincipal());
        $vessels = array_intersect_key(LegacyDb::vesselNames(), array_flip($eligibleVesselIds->all()));
        $today = Carbon::today()->format('Y-m-d');

        $rhPartsIds = DB::connection('legacy')->table('tb_pms_running_hours')->pluck('partsID')->flip();

        $activities = DB::connection('legacy')->table('tb_pms_activities as ta')
            ->leftJoin('tb_pms_running_hours_details as trh', 'trh.partsID', '=', 'ta.partsID')
            ->where('ta.active_status', '1')
            ->select([
                'ta.vesID', 'ta.partsID', 'ta.unit', 'ta.min_count_interval', 'ta.max_count_interval',
                'ta.due_date', 'ta.is_postpone', 'ta.no_of_hours',
                'trh.trh_since_delivery as since_delivery',
            ])
            ->get()
            ->groupBy('vesID');

        return collect($vessels)->map(function (string $name, string $vesID) use ($activities, $rhPartsIds, $today) {
            $upcoming = 0;
            $overdue = 0;
            $postponed = 0;

            foreach ($activities->get($vesID, collect()) as $activity) {
                $isPostponed = (int) $activity->is_postpone === 1;

                if ($isPostponed) {
                    $postponed++;
                }

                $hasRh = $rhPartsIds->has($activity->partsID);

                if (! $hasRh) {
                    $dueDate = $activity->due_date;

                    if ($dueDate === '0000-00-00') {
                        continue;
                    }

                    if ($dueDate < $today) {
                        $overdue++;
                    } elseif (Carbon::parse($dueDate)->subDays(30)->format('Y-m-d') <= $today) {
                        $upcoming++;
                    }

                    continue;
                }

                if ($isPostponed) {
                    continue;
                }

                $hoursPerUnit = self::HOURS_PER_UNIT[$activity->unit] ?? null;

                if ($hoursPerUnit === null) {
                    continue;
                }

                $sinceDelivery = (float) $activity->since_delivery;

                if ($sinceDelivery !== 0.0) {
                    $upcomingInterval = (int) $activity->min_count_interval !== 0 ? $activity->min_count_interval : $activity->max_count_interval;
                    $upcomingRange = ($upcomingInterval * $hoursPerUnit) - $activity->no_of_hours;

                    if ($upcomingRange > 0 && $upcomingRange <= 720) {
                        $upcoming++;
                    }
                }

                $overdueInterval = (int) $activity->max_count_interval !== 0 ? $activity->max_count_interval : $activity->min_count_interval;
                $overdueRange = ($overdueInterval * $hoursPerUnit) - $activity->no_of_hours;

                if ($overdueRange < 0) {
                    $overdue++;
                }
            }

            return ['vessel' => $name, 'upcoming' => $upcoming, 'overdue' => $overdue, 'postponed' => $postponed];
        })->values();
    }

    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $rows = $this->legacySummaries($legacyUserId);

        if ($query->search !== null) {
            $term = mb_strtolower($query->search);
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['vessel']), $term));
        }

        $sortable = [
            'vessel' => fn (array $row) => mb_strtolower($row['vessel']),
            'upcoming' => fn (array $row) => $row['upcoming'],
            'overdue' => fn (array $row) => $row['overdue'],
            'postponed' => fn (array $row) => $row['postponed'],
        ];
        $sortKey = $sortable[$query->sort ?? 'vessel'] ?? $sortable['vessel'];

        $sorted = $rows->sortBy($sortKey, SORT_REGULAR, $query->direction === 'desc')->values();

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
}
