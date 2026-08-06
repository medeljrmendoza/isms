<?php

namespace App\Repositories\ExposureHours;

use App\Support\LegacyDb;
use App\Support\TableQuery;
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
     * here. Reads tb_exposure_hours_records directly from the legacy
     * connection, keeping the vesID-in-assigned-vessels scoping the
     * dashlet also uses.
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
     * Same as the local fullTable(), reading tb_exposure_hours_records
     * directly from the legacy connection. Read-only: can_edit/can_delete
     * are always false.
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

    private function frequencyRate(int $incidents, float $totalHours): float
    {
        return $totalHours != 0.0 ? round(($incidents * 1_000_000) / $totalHours, 2) : 0.0;
    }
}
