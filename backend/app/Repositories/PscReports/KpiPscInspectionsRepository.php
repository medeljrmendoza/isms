<?php

namespace App\Repositories\PscReports;

use App\Models\Nonconformities\Nonconformity;
use App\Models\PscReports\PscMouAuthority;
use App\Models\PscReports\PscReport;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Ported from Controllers/Kpi_psc_inspections.php. A pure reporting
 * layer over the already-migrated PscReport/Nonconformity data — no new
 * tables. Not ported: the "Observations per Vessel" chart — no
 * Observations module exists anywhere in this migration (see
 * PscReportRepository's docblock), so there's nothing to aggregate.
 * Also not ported: the ACTIVE-vessel-status and per-user vessel scoping
 * on the vessel list — same drop as every other module (vessels here
 * have no status column at all, and per-user scoping is dropped
 * project-wide).
 */
class KpiPscInspectionsRepository
{
    private const REPORT_COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'dateof_inspection', 'label' => 'DATE OF INSPECTION', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PORT OF INSPECTION', 'sortable' => false],
        ['key' => 'name_psco', 'label' => 'NAME OF INSPECTOR', 'sortable' => false],
        ['key' => 'mou', 'label' => 'PSC MOU', 'sortable' => false],
    ];

    private const MOU_REPORT_COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'dateof_inspection', 'label' => 'DATE OF INSPECTION', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PORT OF INSPECTION', 'sortable' => false],
        ['key' => 'name_psco', 'label' => 'NAME OF INSPECTOR', 'sortable' => false],
    ];

    private const NONCONFORMITY_COLUMNS = [
        ['key' => 'ncr_no', 'label' => 'NCR NO.', 'sortable' => true],
        ['key' => 'date_of_nc', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'source_of_nc_ref_no', 'label' => 'SOURCE', 'sortable' => false],
        ['key' => 'description', 'label' => 'DESCRIPTION', 'sortable' => false],
        ['key' => 'root_cause', 'label' => 'ROOT CAUSE', 'sortable' => false],
        ['key' => 'corrective_action', 'label' => 'C.A.R.', 'sortable' => false],
        ['key' => 'verification', 'label' => 'VERIFICATION', 'sortable' => false],
    ];

    public static function reportColumns(): array
    {
        return self::REPORT_COLUMNS;
    }

    public static function mouReportColumns(): array
    {
        return self::MOU_REPORT_COLUMNS;
    }

    public static function nonconformityColumns(): array
    {
        return self::NONCONFORMITY_COLUMNS;
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function mouOptions(): array
    {
        return PscMouAuthority::query()->orderBy('name')->get()
            ->map(fn (PscMouAuthority $m) => ['id' => $m->id, 'label' => $m->name])
            ->all();
    }

    /**
     * Ported from index()'s filter==0 branch. `$to` defaults to today
     * when only `$from` is given, matching legacy's implicit inclusive
     * upper bound behavior for a same-day range.
     */
    public function reportsPerVessel(?string $from, ?string $to): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => [
                'label' => $v->display_name,
                'count' => $this->scopeDateRange(PscReport::query()->where('vessel_id', $v->id)->where('is_deleted', false), $from, $to)->count(),
            ])
            ->all();
    }

    /** Ported from index()'s filter==1 branch. */
    public function reportsPerMou(?string $from, ?string $to): array
    {
        return PscMouAuthority::query()->orderBy('name')->get()
            ->map(fn (PscMouAuthority $m) => [
                'label' => $m->name,
                'count' => $this->scopeDateRange(PscReport::query()->where('mou_id', $m->id)->where('is_deleted', false), $from, $to)->count(),
            ])
            ->all();
    }

    /**
     * Ported from index()'s filter==2 branch: non-conformities linked
     * to a PSC report for each vessel, via the same loose ref_no
     * relation PscReport::nonconformities() already uses.
     */
    public function nonConformitiesPerVessel(?string $from, ?string $to): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => [
                'label' => $v->display_name,
                'count' => Nonconformity::query()
                    ->where('is_inactive', false)
                    ->whereHas('pscReport', function (Builder $q) use ($v, $from, $to) {
                        $q->where('vessel_id', $v->id)->where('is_deleted', false);
                        $this->scopeDateRange($q, $from, $to, 'dateof_inspection');
                    })
                    ->count(),
            ])
            ->all();
    }

    /** Ported from loadPSCReportsVesselData(). */
    public function reportsByVessel(int $vesselId, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->scopeDateRange(
            PscReport::query()->with('mou')->where('vessel_id', $vesselId)->where('is_deleted', false),
            $from,
            $to,
        );

        $sortable = array_column(array_filter(self::REPORT_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    /** Ported from loadMOUReportData(). */
    public function reportsByMou(int $mouId, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->scopeDateRange(
            PscReport::query()->with('vessel')->where('mou_id', $mouId)->where('is_deleted', false),
            $from,
            $to,
        );

        $sortable = array_column(array_filter(self::MOU_REPORT_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    /** Ported from loadNonConformities(). */
    public function nonConformitiesByVessel(int $vesselId, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = Nonconformity::query()
            ->where('is_inactive', false)
            ->whereHas('pscReport', function (Builder $q) use ($vesselId, $from, $to) {
                $q->where('vessel_id', $vesselId)->where('is_deleted', false);
                $this->scopeDateRange($q, $from, $to, 'dateof_inspection');
            });

        $sortable = array_column(array_filter(self::NONCONFORMITY_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_of_nc';

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
