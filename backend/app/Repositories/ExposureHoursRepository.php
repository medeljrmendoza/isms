<?php

namespace App\Repositories;

use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ExposureHoursRepository
{
    /** Matches the legacy dashlet view's table headers exactly. */
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => true],
        ['key' => 'date_from', 'label' => 'FROM', 'sortable' => true],
        ['key' => 'date_to', 'label' => 'TO', 'sortable' => true],
        ['key' => 'no_of_crew', 'label' => 'CREW', 'sortable' => true],
        ['key' => 'no_of_fat', 'label' => 'FAT', 'sortable' => true],
        ['key' => 'no_of_ptd', 'label' => 'PTD', 'sortable' => true],
        ['key' => 'no_of_ppd', 'label' => 'PPD', 'sortable' => true],
        ['key' => 'no_of_lwc', 'label' => 'LWC', 'sortable' => true],
        ['key' => 'no_of_rwc', 'label' => 'RWC', 'sortable' => true],
        ['key' => 'no_of_mtc', 'label' => 'MTC', 'sortable' => true],
        ['key' => 'total_hours', 'label' => 'TOTAL HOURS', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_exposure_hours.php's loadData():
     * one row per vessel, showing only its most recent reporting period.
     * Not scoped by vessel/user — same deferral as the other dashlets.
     *
     * Legacy LEFT JOINs every vessel in scope, so vessels with no record
     * yet would appear with blank data; here we drop those instead —
     * a vessel with nothing to report isn't useful in a dashboard
     * summary list.
     */
    public function latestPerVessel(): Collection
    {
        return Vessel::query()
            ->whereHas('latestExposureHoursRecord')
            ->with('latestExposureHoursRecord')
            ->get()
            ->sortBy('name')
            ->values();
    }

    /**
     * Search/sort/paginate done in PHP rather than SQL: the row set here
     * is one entry per vessel (bounded by fleet size, not a growing
     * transactional table), and every sortable/searchable field lives on
     * the *related* latest record, which doesn't map to a single clean
     * SQL query the way a direct table column would.
     */
    public function table(TableQuery $query): LengthAwarePaginator
    {
        $vessels = $this->latestPerVessel();

        if ($query->search !== null) {
            $term = mb_strtolower($query->search);
            $vessels = $vessels->filter(function (Vessel $vessel) use ($term) {
                $record = $vessel->latestExposureHoursRecord;

                return str_contains(mb_strtolower($vessel->display_name), $term)
                    || str_contains(mb_strtolower((string) $record->date_from), $term)
                    || str_contains(mb_strtolower((string) $record->date_to), $term)
                    || str_contains((string) $record->total_hours, $term);
            });
        }

        $sortable = [
            'vessel' => fn (Vessel $v) => $v->display_name,
            'date_from' => fn (Vessel $v) => (string) $v->latestExposureHoursRecord->date_from,
            'date_to' => fn (Vessel $v) => (string) $v->latestExposureHoursRecord->date_to,
            'no_of_crew' => fn (Vessel $v) => $v->latestExposureHoursRecord->no_of_crew,
            'no_of_fat' => fn (Vessel $v) => $v->latestExposureHoursRecord->no_of_fat,
            'no_of_ptd' => fn (Vessel $v) => $v->latestExposureHoursRecord->no_of_ptd,
            'no_of_ppd' => fn (Vessel $v) => $v->latestExposureHoursRecord->no_of_ppd,
            'no_of_lwc' => fn (Vessel $v) => $v->latestExposureHoursRecord->no_of_lwc,
            'no_of_rwc' => fn (Vessel $v) => $v->latestExposureHoursRecord->no_of_rwc,
            'no_of_mtc' => fn (Vessel $v) => $v->latestExposureHoursRecord->no_of_mtc,
            'total_hours' => fn (Vessel $v) => (float) $v->latestExposureHoursRecord->total_hours,
        ];
        $sortKey = $sortable[$query->sort] ?? $sortable['vessel'];

        $sorted = $vessels->sortBy($sortKey, SORT_REGULAR, $query->direction === 'desc')->values();

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
