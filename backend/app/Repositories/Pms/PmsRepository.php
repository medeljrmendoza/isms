<?php

namespace App\Repositories\Pms;

use App\Repositories\Drills\DrillRepository;

use App\Models\Pms\PmsActivity;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    /** @return Collection<int, array{vessel: Vessel, upcoming: int, overdue: int, postponed: int}> */
    public function summaries(): Collection
    {
        $activities = PmsActivity::query()->where('is_active', true)->get()->groupBy('vessel_id');
        $today = Carbon::today();

        return Vessel::query()->orderBy('name')->get()->map(function (Vessel $vessel) use ($activities, $today) {
            $upcoming = 0;
            $overdue = 0;
            $postponed = 0;

            foreach ($activities->get($vessel->id, collect()) as $activity) {
                if ($activity->is_postponed) {
                    $postponed++;

                    continue;
                }

                if ($activity->since_delivery === null) {
                    if ($activity->due_date === null) {
                        continue;
                    }

                    if ($activity->due_date->lt($today)) {
                        $overdue++;
                    } elseif ($activity->due_date->copy()->subDays(30)->lte($today)) {
                        $upcoming++;
                    }

                    continue;
                }

                $hoursPerUnit = self::HOURS_PER_UNIT[$activity->unit];

                // Upcoming requires a nonzero running-hours meter, matching legacy's
                // `since_delivery != "0"` guard (absent from the overdue branch below).
                if ($activity->since_delivery !== 0.0) {
                    $upcomingInterval = $activity->min_count_interval !== 0 ? $activity->min_count_interval : $activity->max_count_interval;
                    $upcomingRange = ($upcomingInterval * $hoursPerUnit) - $activity->no_of_hours;

                    if ($upcomingRange > 0 && $upcomingRange <= 720) {
                        $upcoming++;
                    }
                }

                $overdueInterval = $activity->max_count_interval !== 0 ? $activity->max_count_interval : $activity->min_count_interval;
                $overdueRange = ($overdueInterval * $hoursPerUnit) - $activity->no_of_hours;

                // Legacy's overdue check is `<= 720 && < 0` — the <=720 half is a
                // no-op for any negative number, so this is really just `< 0`.
                if ($overdueRange < 0) {
                    $overdue++;
                }
            }

            return ['vessel' => $vessel, 'upcoming' => $upcoming, 'overdue' => $overdue, 'postponed' => $postponed];
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
            'postponed' => fn (array $row) => $row['postponed'],
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
}
