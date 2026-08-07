<?php

namespace App\Repositories\NonSire;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Kpi_non_sire.php. Two of the four legacy
 * filters are portable: Reports per Vessel and Reports per Inspection
 * Type. Observations per Vessel and Observations per Inspection Type
 * are dropped — no Observations module exists anywhere in this
 * migration.
 */
class KpiNonSireRepository
{
    private const REPORT_COLUMNS = [
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => false],
        ['key' => 'company_name', 'label' => 'COMPANY', 'sortable' => false],
        ['key' => 'inspector_name', 'label' => 'INSPECTOR', 'sortable' => false],
    ];

    private const INSPECTION_TYPE_REPORT_COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => false],
        ['key' => 'company_name', 'label' => 'COMPANY', 'sortable' => false],
        ['key' => 'inspector_name', 'label' => 'INSPECTOR', 'sortable' => false],
    ];

    public static function reportColumns(): array
    {
        return self::REPORT_COLUMNS;
    }

    public static function inspectionTypeReportColumns(): array
    {
        return self::INSPECTION_TYPE_REPORT_COLUMNS;
    }

    /**
     * Ported from Kpi_non_sire::index()'s $vessels query: ACTIVE
     * vessels the given legacy user is assigned to, sorted by name.
     *
     * @return array<int, array{id:string,label:string}>
     */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        $names = LegacyDb::vesselNames();
        $ids = LegacyDb::assignedVesselIds($legacyUserId)->intersect(LegacyDb::activeVesselIds());

        return $ids->map(fn ($id) => ['id' => $id, 'label' => $names[$id] ?? ''])
            ->sortBy('label')->values()->all();
    }

    /** Ported from index()'s filter==0 branch, reading tb_non_sire directly. */
    public function legacyReportsPerVessel(?string $from, ?string $to, ?string $legacyUserId): array
    {
        return collect($this->legacyVesselOptions($legacyUserId))
            ->map(function (array $v) use ($from, $to) {
                $q = DB::connection('legacy')->table('tb_non_sire')
                    ->join('tb_vessel', 'tb_vessel.vesID', '=', 'tb_non_sire.vesID')
                    ->where('tb_non_sire.vesID', $v['id'])
                    ->where('tb_vessel.vessel_status', 'ACTIVE');
                $this->legacyScopeDateRange($q, $from, $to, 'tb_non_sire.dateof_inspection');

                return ['label' => $v['label'], 'count' => $q->count()];
            })->all();
    }

    /** Ported from index()'s filter==2 branch, iterating the real pl_non_sire_inspection_type lookup. */
    public function legacyReportsPerInspectionType(?string $from, ?string $to, ?string $legacyUserId): array
    {
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        return DB::connection('legacy')->table('pl_non_sire_inspection_type')->where('status', '1')->orderBy('inspection_type')->get()
            ->map(function ($t) use ($from, $to, $assignedVesselIds) {
                $q = DB::connection('legacy')->table('tb_non_sire')
                    ->join('tb_vessel', 'tb_vessel.vesID', '=', 'tb_non_sire.vesID')
                    ->where('tb_non_sire.inspectionTypeID', $t->inspectionTypeID)
                    ->where('tb_non_sire.is_deleted', '0')
                    ->where('tb_vessel.vessel_status', 'ACTIVE')
                    ->whereIn('tb_non_sire.vesID', $assignedVesselIds);
                $this->legacyScopeDateRange($q, $from, $to, 'tb_non_sire.dateof_inspection');

                return ['label' => $t->inspection_type, 'count' => $q->count()];
            })->all();
    }

    /** Ported from loadNonSIREReportsVesselData(). */
    public function legacyReportsByVessel(string $vesselId, ?string $from, ?string $to, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_non_sire')
            ->where('vesID', $vesselId)->where('is_deleted', '0')
            ->select(['nonsireID', 'dateof_inspection', 'added_by', 'placeof_inspection', 'company', 'inspector']);
        $this->legacyScopeDateRange($builder, $from, $to);

        $sortMap = ['dateof_inspection' => 'dateof_inspection', 'added_by' => 'added_by'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'dateof_inspection';
        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->nonsireID,
            'dateof_inspection' => $r->dateof_inspection,
            'added_by' => $r->added_by,
            'placeof_inspection' => $r->placeof_inspection,
            'company_name' => (LegacyDb::addressBookEntry($r->company) ?? [])['company'] ?? '',
            'inspector_name' => (LegacyDb::addressBookEntry($r->inspector) ?? [])['name'] ?? '',
        ])->all();

        return ['rows' => $rows, 'meta' => $this->legacyMeta($paginator)];
    }

    /** Ported from loadNonSIREReportsInspectionData(). */
    public function legacyReportsByInspectionType(string $inspectionType, ?string $from, ?string $to, TableQuery $query, ?string $legacyUserId): array
    {
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);
        $vessels = LegacyDb::vesselNames();

        $builder = DB::connection('legacy')->table('tb_non_sire')
            ->join('pl_non_sire_inspection_type', 'pl_non_sire_inspection_type.inspectionTypeID', '=', 'tb_non_sire.inspectionTypeID')
            ->join('tb_vessel', 'tb_vessel.vesID', '=', 'tb_non_sire.vesID')
            ->where('pl_non_sire_inspection_type.inspection_type', $inspectionType)
            ->where('tb_vessel.vessel_status', 'ACTIVE')
            ->whereIn('tb_non_sire.vesID', $assignedVesselIds)
            ->where('tb_non_sire.is_deleted', '0')
            ->select(['tb_non_sire.nonsireID', 'tb_non_sire.vesID', 'tb_non_sire.added_by', 'tb_non_sire.dateof_inspection', 'tb_non_sire.placeof_inspection', 'tb_non_sire.company', 'tb_non_sire.inspector']);
        $this->legacyScopeDateRange($builder, $from, $to, 'tb_non_sire.dateof_inspection');

        $sortMap = ['dateof_inspection' => 'tb_non_sire.dateof_inspection', 'added_by' => 'tb_non_sire.added_by'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_non_sire.dateof_inspection';
        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->nonsireID,
            'vessel' => $vessels[$r->vesID] ?? '',
            'added_by' => $r->added_by,
            'dateof_inspection' => $r->dateof_inspection,
            'placeof_inspection' => $r->placeof_inspection,
            'company_name' => (LegacyDb::addressBookEntry($r->company) ?? [])['company'] ?? '',
            'inspector_name' => (LegacyDb::addressBookEntry($r->inspector) ?? [])['name'] ?? '',
        ])->all();

        return ['rows' => $rows, 'meta' => $this->legacyMeta($paginator)];
    }

    private function legacyScopeDateRange(QueryBuilder $builder, ?string $from, ?string $to, string $column = 'dateof_inspection'): QueryBuilder
    {
        if ($from !== null && $from !== '') {
            return $builder->where($column, '>=', $from)->where($column, '<=', $to ?: $from);
        }

        return $builder->whereYear($column, Carbon::now()->year);
    }

    private function legacyMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
