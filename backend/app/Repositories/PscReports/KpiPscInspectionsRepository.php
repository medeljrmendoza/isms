<?php

namespace App\Repositories\PscReports;

use App\Models\Nonconformities\Nonconformity;
use App\Models\PscReports\PscMouAuthority;
use App\Models\PscReports\PscReport;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    /**
     * Ported from Kpi_psc_inspections::index()'s $vessels query: ACTIVE
     * vessels the given legacy user is assigned to, sorted by name —
     * the vessel set every per-vessel chart and drill-down iterates.
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

    /** Ported from the $mous query: only MOUs with status='1'. */
    public function legacyMouOptions(): array
    {
        return DB::connection('legacy')->table('tb_psc_mou')
            ->where('status', '1')->orderBy('mou_name')
            ->get()->map(fn ($m) => ['id' => $m->mouID, 'label' => $m->mou_name])->all();
    }

    /** Ported from index()'s filter==0 branch, reading tb_psc_report directly. */
    public function legacyReportsPerVessel(?string $from, ?string $to, ?string $legacyUserId): array
    {
        return collect($this->legacyVesselOptions($legacyUserId))
            ->map(function (array $v) use ($from, $to) {
                $q = DB::connection('legacy')->table('tb_psc_report')
                    ->where('vesid', $v['id'])->where('is_deleted', '0');
                $this->legacyScopeDateRange($q, $from, $to);

                return ['label' => $v['label'], 'count' => $q->count()];
            })->all();
    }

    /** Ported from index()'s filter==1 branch. */
    public function legacyReportsPerMou(?string $from, ?string $to, ?string $legacyUserId): array
    {
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        return DB::connection('legacy')->table('tb_psc_mou')->where('status', '1')->orderBy('mou_name')->get()
            ->map(function ($m) use ($from, $to, $assignedVesselIds) {
                $q = DB::connection('legacy')->table('tb_psc_report')
                    ->join('tb_vessel', 'tb_vessel.vesID', '=', 'tb_psc_report.vesid')
                    ->where('tb_vessel.vessel_status', 'ACTIVE')
                    ->where('tb_psc_report.mouID', $m->mouID)
                    ->where('tb_psc_report.is_deleted', '0')
                    ->whereIn('tb_psc_report.vesid', $assignedVesselIds);
                $this->legacyScopeDateRange($q, $from, $to, 'tb_psc_report.dateof_inspection');

                return ['label' => $m->mou_name, 'count' => $q->count()];
            })->all();
    }

    /** Ported from index()'s filter==2 branch. */
    public function legacyNonConformitiesPerVessel(?string $from, ?string $to, ?string $legacyUserId): array
    {
        return collect($this->legacyVesselOptions($legacyUserId))
            ->map(function (array $v) use ($from, $to) {
                $q = DB::connection('legacy')->table('tb_nonconformities')
                    ->join('tb_psc_report', 'tb_psc_report.ref_no', '=', 'tb_nonconformities.source_of_nc_ref_no')
                    ->where('tb_psc_report.vesid', $v['id'])
                    ->where('tb_nonconformities.is_inactive', '0')
                    ->where('tb_psc_report.is_deleted', '0');
                $this->legacyScopeDateRange($q, $from, $to, 'tb_psc_report.dateof_inspection');

                return ['label' => $v['label'], 'count' => $q->count()];
            })->all();
    }

    /** Ported from loadPSCReportsVesselData(). */
    public function legacyReportsByVessel(string $vesselId, ?string $from, ?string $to, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_psc_report')
            ->leftJoin('tb_psc_mou', 'tb_psc_mou.mouID', '=', 'tb_psc_report.mouID')
            ->where('tb_psc_report.vesid', $vesselId)
            ->where('tb_psc_report.is_deleted', '0')
            ->select(['tb_psc_report.pscreportid', 'tb_psc_report.ref_no', 'tb_psc_report.dateof_inspection', 'tb_psc_report.placeof_inspection', 'tb_psc_report.name_psco', 'tb_psc_report.mou_others', 'tb_psc_mou.mou_name']);
        $this->legacyScopeDateRange($builder, $from, $to, 'tb_psc_report.dateof_inspection');

        $sortMap = ['ref_no' => 'tb_psc_report.ref_no', 'dateof_inspection' => 'tb_psc_report.dateof_inspection'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_psc_report.dateof_inspection';
        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->pscreportid,
            'ref_no' => $r->ref_no,
            'dateof_inspection' => $r->dateof_inspection,
            'placeof_inspection' => $r->placeof_inspection,
            'name_psco' => $r->name_psco,
            'mou' => $r->mou_name === null ? '' : ($r->mou_name === 'Others' ? "MOU - {$r->mou_others}" : $r->mou_name),
        ])->all();

        return ['rows' => $rows, 'meta' => $this->legacyMeta($paginator)];
    }

    /** Ported from loadMOUReportData(). */
    public function legacyReportsByMou(string $mouId, ?string $from, ?string $to, TableQuery $query, ?string $legacyUserId): array
    {
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);
        $vessels = LegacyDb::vesselNames();

        $builder = DB::connection('legacy')->table('tb_psc_report')
            ->join('tb_vessel', 'tb_vessel.vesID', '=', 'tb_psc_report.vesid')
            ->where('tb_psc_report.mouID', $mouId)
            ->where('tb_psc_report.is_deleted', '0')
            ->where('tb_vessel.vessel_status', 'ACTIVE')
            ->whereIn('tb_psc_report.vesid', $assignedVesselIds)
            ->select(['tb_psc_report.pscreportid', 'tb_psc_report.ref_no', 'tb_psc_report.vesid', 'tb_psc_report.dateof_inspection', 'tb_psc_report.placeof_inspection', 'tb_psc_report.name_psco']);
        $this->legacyScopeDateRange($builder, $from, $to, 'tb_psc_report.dateof_inspection');

        $sortMap = ['ref_no' => 'tb_psc_report.ref_no', 'dateof_inspection' => 'tb_psc_report.dateof_inspection'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_psc_report.dateof_inspection';
        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->pscreportid,
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesid] ?? '',
            'dateof_inspection' => $r->dateof_inspection,
            'placeof_inspection' => $r->placeof_inspection,
            'name_psco' => $r->name_psco,
        ])->all();

        return ['rows' => $rows, 'meta' => $this->legacyMeta($paginator)];
    }

    /** Ported from loadNonConformities(). */
    public function legacyNonConformitiesByVessel(string $vesselId, ?string $from, ?string $to, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_nonconformities')
            ->join('tb_psc_report', 'tb_psc_report.ref_no', '=', 'tb_nonconformities.source_of_nc_ref_no')
            ->where('tb_psc_report.vesid', $vesselId)
            ->where('tb_psc_report.is_deleted', '0')
            ->where('tb_nonconformities.is_inactive', '0')
            ->select(['tb_nonconformities.ncID', 'tb_nonconformities.ncr_no', 'tb_nonconformities.date_of_nc', 'tb_nonconformities.source_of_nc_ref_no', 'tb_nonconformities.description', 'tb_nonconformities.root_cause', 'tb_nonconformities.corrective_action', 'tb_nonconformities.verification']);
        $this->legacyScopeDateRange($builder, $from, $to, 'tb_psc_report.dateof_inspection');

        $sortMap = ['ncr_no' => 'tb_nonconformities.ncr_no', 'date_of_nc' => 'tb_nonconformities.date_of_nc'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_nonconformities.date_of_nc';
        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->ncID,
            'ncr_no' => $r->ncr_no,
            'date_of_nc' => $r->date_of_nc,
            'source_of_nc_ref_no' => $r->source_of_nc_ref_no,
            'description' => $r->description,
            'root_cause' => $r->root_cause,
            'corrective_action' => $r->corrective_action,
            'verification' => $r->verification,
        ])->all();

        return ['rows' => $rows, 'meta' => $this->legacyMeta($paginator)];
    }

    private function legacyScopeDateRange(QueryBuilder $builder, ?string $from, ?string $to, string $column = 'tb_psc_report.dateof_inspection'): QueryBuilder
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
