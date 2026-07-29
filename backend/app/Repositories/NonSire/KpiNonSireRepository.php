<?php

namespace App\Repositories\NonSire;

use App\Models\NonSire\NonSireReport;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

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
}
