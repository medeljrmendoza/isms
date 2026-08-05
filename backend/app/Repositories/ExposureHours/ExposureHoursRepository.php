<?php

namespace App\Repositories\ExposureHours;

use App\Models\ExposureHours\ExposureHoursRecord;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /** The per-vessel Records list's column set — see Controllers/Exposure_hours.php's loadData(). */
    private const RECORD_COLUMNS = [
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
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
        ['key' => 'vessel_remarks', 'label' => 'VESSEL REMARKS', 'sortable' => false],
        ['key' => 'shore_remarks', 'label' => 'SHORE REMARKS', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    public static function recordColumns(): array
    {
        return self::RECORD_COLUMNS;
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
        $sortKey = $sortable[$query->sort ?? ''] ?? $sortable['vessel'];

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

    /**
     * Ported from Dashboard_exposure_hours.php's loadData(): one row per
     * vessel showing its most recent reporting period, scoped to the
     * logged-in user's assigned vessels.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $latestPerVessel = DB::connection('legacy')->table('tb_exposure_hours_records')
            ->whereIn('vesID', $assignedVesselIds)
            ->orderByDesc('date_from')
            ->get()
            ->unique('vesID')
            ->keyBy('vesID');

        $rows = collect($assignedVesselIds)
            ->filter(fn ($vesID) => $latestPerVessel->has($vesID))
            ->map(function ($vesID) use ($latestPerVessel, $vessels) {
                $r = $latestPerVessel->get($vesID);

                return [
                    'vessel' => $vessels[$vesID] ?? '',
                    'date_from' => $r->date_from,
                    'date_to' => $r->date_to,
                    'no_of_crew' => $r->no_of_crew,
                    'no_of_fat' => $r->no_of_fat,
                    'no_of_ptd' => $r->no_of_ptd,
                    'no_of_ppd' => $r->no_of_ppd,
                    'no_of_lwc' => $r->no_of_lwc,
                    'no_of_rwc' => $r->no_of_rwc,
                    'no_of_mtc' => $r->no_of_mtc,
                    'total_hours' => number_format((float) $r->total_hours),
                    '_sort_vessel' => $vessels[$vesID] ?? '',
                    '_sort_total' => (float) $r->total_hours,
                ];
            })
            ->values();

        if ($query->search !== null) {
            $term = mb_strtolower($query->search);
            $rows = $rows->filter(fn (array $r) => str_contains(mb_strtolower($r['vessel']), $term)
                || str_contains(mb_strtolower((string) $r['date_from']), $term)
                || str_contains(mb_strtolower((string) $r['date_to']), $term));
        }

        $sortMap = [
            'vessel' => '_sort_vessel',
            'total_hours' => '_sort_total',
        ];
        $sortKey = $sortMap[$query->sort ?? 'vessel'] ?? $query->sort ?? '_sort_vessel';
        $sorted = $rows->sortBy($sortKey, SORT_REGULAR, $query->direction === 'desc')->values()
            ->map(fn (array $r) => collect($r)->except(['_sort_vessel', '_sort_total'])->all());

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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /**
     * Ported from Controllers/Exposure_hours.php's index(): one row per
     * vessel with summed FAT/PTD/PPD/LWC/RWC/MTC/total hours and the
     * computed LTI/TRC/LTIF/TRCF safety metrics, plus a grand total row.
     * LTIF/TRCF are frequency rates per million exposure hours — legacy
     * deliberately does NOT sum each vessel's own LTIF/TRCF into the
     * grand total (that's commented out in the source); the total row
     * recomputes them from the grand LTI/TRC/total_hours instead, same
     * here.
     */
    public function summary(?string $vesselId, ?string $dateFrom, ?string $dateTo): array
    {
        $vesselsQuery = Vessel::query()->orderBy('name');
        if ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $vesselsQuery->where('id', $vesselId);
        }

        $totals = ['fat' => 0, 'ptd' => 0, 'ppd' => 0, 'lwc' => 0, 'rwc' => 0, 'mtc' => 0, 'total_hours' => 0.0, 'lti' => 0, 'trc' => 0];
        $rows = [];

        foreach ($vesselsQuery->get() as $vessel) {
            $records = $this->recordsQuery($vessel->id, $dateFrom, $dateTo)->get();

            $fat = (int) $records->sum('no_of_fat');
            $ptd = (int) $records->sum('no_of_ptd');
            $ppd = (int) $records->sum('no_of_ppd');
            $lwc = (int) $records->sum('no_of_lwc');
            $rwc = (int) $records->sum('no_of_rwc');
            $mtc = (int) $records->sum('no_of_mtc');
            $totalHours = (float) $records->sum('total_hours');

            $lti = $fat + $lwc + $ptd + $ppd;
            $trc = $lti + $rwc + $mtc;

            $rows[] = [
                'vessel_id' => $vessel->id,
                'vessel' => $vessel->display_name,
                'no_of_fat' => $fat,
                'no_of_ptd' => $ptd,
                'no_of_ppd' => $ppd,
                'no_of_lwc' => $lwc,
                'no_of_rwc' => $rwc,
                'no_of_mtc' => $mtc,
                'total_hours' => $totalHours,
                'lti' => $lti,
                'trc' => $trc,
                'ltif' => $this->frequencyRate($lti, $totalHours),
                'trcf' => $this->frequencyRate($trc, $totalHours),
            ];

            $totals['fat'] += $fat;
            $totals['ptd'] += $ptd;
            $totals['ppd'] += $ppd;
            $totals['lwc'] += $lwc;
            $totals['rwc'] += $rwc;
            $totals['mtc'] += $mtc;
            $totals['total_hours'] += $totalHours;
            $totals['lti'] += $lti;
            $totals['trc'] += $trc;
        }

        $totals['ltif'] = $this->frequencyRate($totals['lti'], $totals['total_hours']);
        $totals['trcf'] = $this->frequencyRate($totals['trc'], $totals['total_hours']);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Same as summary(), reading tb_exposure_hours_records directly
     * from the legacy connection. Keeps the vesID-in-assigned-vessels
     * scoping the local queries drop project-wide (see other
     * repositories' same docblock note).
     */
    public function legacySummary(?string $vesselId, ?string $dateFrom, ?string $dateTo, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $vesselIds = ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL')
            ? $assignedVesselIds->filter(fn ($id) => $id === $vesselId)
            : $assignedVesselIds;

        $totals = ['fat' => 0, 'ptd' => 0, 'ppd' => 0, 'lwc' => 0, 'rwc' => 0, 'mtc' => 0, 'total_hours' => 0.0, 'lti' => 0, 'trc' => 0];
        $rows = [];

        foreach ($vesselIds->sort(fn ($a, $b) => ($vessels[$a] ?? '') <=> ($vessels[$b] ?? '')) as $vesID) {
            $records = $this->legacyRecordsQuery($vesID, $dateFrom, $dateTo)->get();

            $fat = (int) $records->sum('no_of_fat');
            $ptd = (int) $records->sum('no_of_ptd');
            $ppd = (int) $records->sum('no_of_ppd');
            $lwc = (int) $records->sum('no_of_lwc');
            $rwc = (int) $records->sum('no_of_rwc');
            $mtc = (int) $records->sum('no_of_mtc');
            $totalHours = (float) $records->sum('total_hours');

            $lti = $fat + $lwc + $ptd + $ppd;
            $trc = $lti + $rwc + $mtc;

            $rows[] = [
                'vessel_id' => $vesID,
                'vessel' => $vessels[$vesID] ?? '',
                'no_of_fat' => $fat,
                'no_of_ptd' => $ptd,
                'no_of_ppd' => $ppd,
                'no_of_lwc' => $lwc,
                'no_of_rwc' => $rwc,
                'no_of_mtc' => $mtc,
                'total_hours' => $totalHours,
                'lti' => $lti,
                'trc' => $trc,
                'ltif' => $this->frequencyRate($lti, $totalHours),
                'trcf' => $this->frequencyRate($trc, $totalHours),
            ];

            $totals['fat'] += $fat;
            $totals['ptd'] += $ptd;
            $totals['ppd'] += $ppd;
            $totals['lwc'] += $lwc;
            $totals['rwc'] += $rwc;
            $totals['mtc'] += $mtc;
            $totals['total_hours'] += $totalHours;
            $totals['lti'] += $lti;
            $totals['trc'] += $trc;
        }

        $totals['ltif'] = $this->frequencyRate($totals['lti'], $totals['total_hours']);
        $totals['trcf'] = $this->frequencyRate($totals['trc'], $totals['total_hours']);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Same as fullTable(), reading tb_exposure_hours_records directly
     * from the legacy connection. Read-only: can_edit/can_delete are
     * always false.
     */
    public function legacyFullTable(string $vesselId, ?string $dateFrom, ?string $dateTo, TableQuery $query, ?string $legacyUserId): array
    {
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        if (! $assignedVesselIds->contains($vesselId)) {
            return ['rows' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $query->perPage, 'total' => 0]];
        }

        $builder = $this->legacyRecordsQuery($vesselId, $dateFrom, $dateTo);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('date_from', 'like', $term)
                    ->orWhere('date_to', 'like', $term)
                    ->orWhere('vessel_remarks', 'like', $term)
                    ->orWhere('shore_remarks', 'like', $term);
            });
        }

        $sortMap = [
            'added_by' => 'added_by', 'date_from' => 'date_from', 'date_to' => 'date_to',
            'no_of_crew' => 'no_of_crew', 'no_of_fat' => 'no_of_fat', 'no_of_ptd' => 'no_of_ptd',
            'no_of_ppd' => 'no_of_ppd', 'no_of_lwc' => 'no_of_lwc', 'no_of_rwc' => 'no_of_rwc',
            'no_of_mtc' => 'no_of_mtc', 'total_hours' => 'total_hours',
        ];
        $sort = $sortMap[$query->sort ?? ''] ?? 'date_from';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->ehRecordID,
            'added_by' => $r->added_by,
            'date_from' => $r->date_from,
            'date_to' => $r->date_to,
            'no_of_crew' => $r->no_of_crew,
            'no_of_fat' => $r->no_of_fat,
            'no_of_ptd' => $r->no_of_ptd,
            'no_of_ppd' => $r->no_of_ppd,
            'no_of_lwc' => $r->no_of_lwc,
            'no_of_rwc' => $r->no_of_rwc,
            'no_of_mtc' => $r->no_of_mtc,
            'total_hours' => $r->total_hours,
            'vessel_remarks' => $r->vessel_remarks,
            'shore_remarks' => $r->shore_remarks,
            'can_edit' => false,
            'can_delete' => false,
        ])->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Same as show()'s mapDetail(), reading tb_exposure_hours_records
     * directly from the legacy connection.
     */
    public function legacyDetail(string $ehRecordID): ?array
    {
        $r = DB::connection('legacy')->table('tb_exposure_hours_records')->where('ehRecordID', $ehRecordID)->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();

        return [
            'id' => $r->ehRecordID,
            'added_by' => $r->added_by,
            'date_from' => $r->date_from,
            'date_to' => $r->date_to,
            'no_of_crew' => $r->no_of_crew,
            'no_of_fat' => $r->no_of_fat,
            'no_of_ptd' => $r->no_of_ptd,
            'no_of_ppd' => $r->no_of_ppd,
            'no_of_lwc' => $r->no_of_lwc,
            'no_of_rwc' => $r->no_of_rwc,
            'no_of_mtc' => $r->no_of_mtc,
            'total_hours' => $r->total_hours,
            'vessel_remarks' => $r->vessel_remarks,
            'shore_remarks' => $r->shore_remarks,
            'can_edit' => false,
            'can_delete' => false,
            'vessel_id' => $r->vesID,
            'vessel' => $vessels[$r->vesID] ?? '',
        ];
    }

    private function legacyRecordsQuery(string $vesselId, ?string $dateFrom, ?string $dateTo)
    {
        $builder = DB::connection('legacy')->table('tb_exposure_hours_records')->where('vesID', $vesselId);

        if ($dateFrom !== null && $dateFrom !== '') {
            $builder->where('date_from', '>=', $dateFrom)->where('date_to', '<=', $dateTo);
        }

        return $builder;
    }

    /**
     * Ported from Controllers/Exposure_hours.php's records()/loadData():
     * the full record list for one specific vessel.
     */
    public function fullTable(int $vesselId, ?string $dateFrom, ?string $dateTo, TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->recordsQuery($vesselId, $dateFrom, $dateTo);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('date_from', 'like', $term)
                    ->orWhere('date_to', 'like', $term)
                    ->orWhere('vessel_remarks', 'like', $term)
                    ->orWhere('shore_remarks', 'like', $term);
            });
        }

        $sortable = array_column(array_filter(self::RECORD_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_from';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from add_record()'s date-overlap check: four redundant
     * conditions in legacy that all reduce to the standard interval-
     * overlap test — a new/edited period may not overlap any other
     * existing period for the same vessel. True means "blocked".
     */
    public function overlapsExisting(int $vesselId, string $dateFrom, string $dateTo, ?int $ignoreId): bool
    {
        return ExposureHoursRecord::query()
            ->where('vessel_id', $vesselId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('date_from', '<=', $dateTo)
            ->where('date_to', '>=', $dateFrom)
            ->exists();
    }

    /**
     * Ported from add_record()'s insert branch: new records are always
     * SHORE-added (there's no VESSEL-origin path reachable from this
     * admin) with vessel_remarks frozen blank. total_hours/no_of_days
     * are computed here rather than trusted from the client.
     */
    public function create(array $data): ExposureHoursRecord
    {
        return ExposureHoursRecord::create([
            ...$data,
            'added_by' => 'SHORE',
            'vessel_remarks' => null,
            'total_hours' => $this->computeTotalHours($data['date_from'], $data['date_to'], $data['no_of_crew']),
        ]);
    }

    /**
     * Ported from add_record()'s edit branch: vessel, added_by, and
     * vessel_remarks are frozen at creation time (legacy always re-reads
     * them from the existing row).
     */
    public function update(ExposureHoursRecord $record, array $data): ExposureHoursRecord
    {
        unset($data['vessel_id']);

        $record->update([
            ...$data,
            'total_hours' => $this->computeTotalHours($data['date_from'], $data['date_to'], $data['no_of_crew']),
        ]);

        return $record;
    }

    /** Ported from delete_record(): a real delete, not a soft one — same as Drill Reports. */
    public function delete(ExposureHoursRecord $record): void
    {
        $record->delete();
    }

    private function recordsQuery(int $vesselId, ?string $dateFrom, ?string $dateTo): Builder
    {
        $builder = ExposureHoursRecord::query()->where('vessel_id', $vesselId);

        if ($dateFrom !== null && $dateFrom !== '') {
            $builder->where('date_from', '>=', $dateFrom)->where('date_to', '<=', $dateTo);
        }

        return $builder;
    }

    private function computeTotalHours(string $dateFrom, string $dateTo, int $noOfCrew): float
    {
        $days = abs(Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo))) + 1;

        return $days * $noOfCrew * 24;
    }

    private function frequencyRate(int $incidents, float $totalHours): float
    {
        return $totalHours != 0.0 ? round(($incidents * 1_000_000) / $totalHours, 2) : 0.0;
    }
}
