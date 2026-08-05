<?php

namespace App\Repositories\NonSire;

use App\Models\NonSire\NonSireReport;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Kpi_non_sire.php. Two of the four legacy
 * filters are portable: Reports per Vessel and Reports per Inspection
 * Type. Observations per Vessel and Observations per Inspection Type
 * are dropped — no Observations module exists anywhere in this
 * migration. Inspection type is grouped by the free-text
 * `inspection_type` column rather than a lookup table — legacy sources
 * it from pl_non_sire_inspection_type (Setup-managed), which isn't
 * migrated; see non_sire_reports' full-record migration for the same
 * decision.
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

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** Ported from index()'s filter==0 branch. */
    public function reportsPerVessel(?string $from, ?string $to): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => [
                'label' => $v->display_name,
                'count' => $this->scopeDateRange(NonSireReport::query()->where('vessel_id', $v->id)->where('is_deleted', false), $from, $to)->count(),
            ])
            ->all();
    }

    /**
     * Ported from index()'s filter==2 branch. Legacy iterates the full
     * pl_non_sire_inspection_type lookup (including types with zero
     * reports); without that table, this groups over the distinct
     * inspection_type values actually present in the data instead.
     */
    public function reportsPerInspectionType(?string $from, ?string $to): array
    {
        $types = NonSireReport::query()->where('is_deleted', false)
            ->whereNotNull('inspection_type')
            ->distinct()
            ->orderBy('inspection_type')
            ->pluck('inspection_type');

        return $types->map(fn (string $type) => [
            'label' => $type,
            'count' => $this->scopeDateRange(NonSireReport::query()->where('inspection_type', $type)->where('is_deleted', false), $from, $to)->count(),
        ])->all();
    }

    /** Ported from loadNonSIREReportsVesselData(). */
    public function reportsByVessel(int $vesselId, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->scopeDateRange(
            NonSireReport::query()->where('vessel_id', $vesselId)->where('is_deleted', false),
            $from,
            $to,
        );

        $sortable = array_column(array_filter(self::REPORT_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    /** Ported from loadNonSIREReportsInspectionData(). */
    public function reportsByInspectionType(string $inspectionType, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->scopeDateRange(
            NonSireReport::query()->with('vessel')->where('inspection_type', $inspectionType)->where('is_deleted', false),
            $from,
            $to,
        );

        $sortable = array_column(array_filter(self::INSPECTION_TYPE_REPORT_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    private function scopeDateRange(Builder $builder, ?string $from, ?string $to, string $column = 'dateof_inspection'): Builder
    {
        if ($from !== null && $from !== '') {
            return $builder->where($column, '>=', $from)->where($column, '<=', $to ?: $from);
        }

        return $builder->whereYear($column, Carbon::now()->year);
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

    /**
     * Ported from index()'s filter==2 branch: the real
     * pl_non_sire_inspection_type lookup exists in legacy, so (unlike
     * the local simplification — see class docblock) this iterates it
     * directly instead of grouping over distinct values in the data.
     */
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
