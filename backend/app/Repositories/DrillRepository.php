<?php

namespace App\Repositories;

use App\Models\DrillList;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ported from Controllers/Dashboard_drill.php. Legacy renders one row
 * per vessel with two AJAX-loaded counts (upcoming/overdue), each
 * linking out to a separate summary page — this migrates that into a
 * single table (one row per vessel, computed columns), consistent with
 * every other dashlet now being a real DataTable. The drill-down
 * summary pages themselves aren't ported (read-only dashlets, same as
 * everywhere else).
 */
class DrillRepository
{
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => true],
        ['key' => 'upcoming', 'label' => 'UPCOMING', 'sortable' => true],
        ['key' => 'overdue', 'label' => 'OVERDUE', 'sortable' => true],
    ];

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

                $nextDrill = Carbon::parse($lastDrillDate)->add(
                    match ($drillList->frequency_type) {
                        'D' => "{$drillList->frequency_count} days",
                        'W' => "{$drillList->frequency_count} weeks",
                        'M' => "{$drillList->frequency_count} months",
                        'Y' => "{$drillList->frequency_count} years",
                    },
                );

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
}
