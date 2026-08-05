<?php

namespace App\Repositories\InternalAudits;

use App\Models\InternalAudits\InternalAuditReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Kpi_internal.php. A pure reporting layer over
 * the already-migrated InternalAuditReport/Nonconformity data — no new
 * tables, same shape as KpiFlagStateRepository. Not ported: the
 * "Observations per Vessel" chart — no Observations module exists
 * anywhere in this migration. The legacy drill-down also LEFT JOINs
 * pl_audit_kinds/pl_audit_types for audit_kind_name/audit_type_name,
 * but neither is in the actual displayed columns list there, and this
 * model has no such lookup relations (typeof_audit is a plain string
 * column), so they're skipped here too.
 */
class KpiInternalAuditsRepository
{
    private const REPORT_COLUMNS = [
        ['key' => 'audit_ref', 'label' => 'AUDIT REF.', 'sortable' => true],
        ['key' => 'this_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_audit', 'label' => 'PLACE OF AUDIT', 'sortable' => false],
        ['key' => 'typeof_audit', 'label' => 'TYPE OF AUDIT', 'sortable' => false],
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

    /** Ported from index()'s filter==0 branch. */
    public function reportsPerVessel(?string $from, ?string $to): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => [
                'label' => $v->display_name,
                'count' => $this->scopeDateRange(InternalAuditReport::query()->where('vessel_id', $v->id)->where('is_deleted', false), $from, $to)->count(),
            ])
            ->all();
    }

    /** Ported from index()'s filter==1 branch. */
    public function nonConformitiesPerVessel(?string $from, ?string $to): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => [
                'label' => $v->display_name,
                'count' => Nonconformity::query()
                    ->where('is_inactive', false)
                    ->whereHas('internalAuditReport', function (Builder $q) use ($v, $from, $to) {
                        $q->where('vessel_id', $v->id)->where('is_deleted', false);
                        $this->scopeDateRange($q, $from, $to, 'this_date');
                    })
                    ->count(),
            ])
            ->all();
    }

    /** Ported from loadInternalReportsVesselData(). */
    public function reportsByVessel(int $vesselId, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->scopeDateRange(
            InternalAuditReport::query()->where('vessel_id', $vesselId)->where('is_deleted', false),
            $from,
            $to,
        );

        $sortable = array_column(array_filter(self::REPORT_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'this_date';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    /** Ported from loadInternalNonConformities(). */
    public function nonConformitiesByVessel(int $vesselId, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = Nonconformity::query()
            ->where('is_inactive', false)
            ->whereHas('internalAuditReport', function (Builder $q) use ($vesselId, $from, $to) {
                $q->where('vessel_id', $vesselId)->where('is_deleted', false);
                $this->scopeDateRange($q, $from, $to, 'this_date');
            });

        $sortable = array_column(array_filter(self::NONCONFORMITY_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_of_nc';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    private function scopeDateRange(Builder $builder, ?string $from, ?string $to, string $column = 'this_date'): Builder
    {
        if ($from !== null && $from !== '') {
            return $builder->where($column, '>=', $from)->where($column, '<=', $to ?: $from);
        }

        return $builder->whereYear($column, Carbon::now()->year);
    }

    /**
     * Ported from Kpi_internal::index()'s $vessels query: ACTIVE
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

    /** Ported from index()'s filter==0 branch, reading tb_internal_audit_report directly. */
    public function legacyReportsPerVessel(?string $from, ?string $to, ?string $legacyUserId): array
    {
        return collect($this->legacyVesselOptions($legacyUserId))
            ->map(function (array $v) use ($from, $to) {
                $q = DB::connection('legacy')->table('tb_internal_audit_report')
                    ->where('vesID', $v['id'])->where('is_deleted', '0');
                $this->legacyScopeDateRange($q, $from, $to);

                return ['label' => $v['label'], 'count' => $q->count()];
            })->all();
    }

    /** Ported from index()'s filter==1 branch. */
    public function legacyNonConformitiesPerVessel(?string $from, ?string $to, ?string $legacyUserId): array
    {
        return collect($this->legacyVesselOptions($legacyUserId))
            ->map(function (array $v) use ($from, $to) {
                $q = DB::connection('legacy')->table('tb_nonconformities')
                    ->join('tb_internal_audit_report', 'tb_internal_audit_report.audit_ref', '=', 'tb_nonconformities.source_of_nc_ref_no')
                    ->where('tb_internal_audit_report.vesID', $v['id'])
                    ->where('tb_nonconformities.is_inactive', '0')
                    ->where('tb_internal_audit_report.is_deleted', '0');
                $this->legacyScopeDateRange($q, $from, $to, 'tb_internal_audit_report.this_date');

                return ['label' => $v['label'], 'count' => $q->count()];
            })->all();
    }

    /** Ported from loadInternalReportsVesselData(). */
    public function legacyReportsByVessel(string $vesselId, ?string $from, ?string $to, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_internal_audit_report')
            ->where('vesID', $vesselId)->where('is_deleted', '0')
            ->select(['auditID', 'audit_ref', 'this_date', 'placeof_audit', 'typeof_audit']);
        $this->legacyScopeDateRange($builder, $from, $to);

        $sortMap = ['audit_ref' => 'audit_ref', 'this_date' => 'this_date'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'this_date';
        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->auditID,
            'audit_ref' => $r->audit_ref,
            'this_date' => $r->this_date,
            'placeof_audit' => $r->placeof_audit,
            'typeof_audit' => $r->typeof_audit,
        ])->all();

        return ['rows' => $rows, 'meta' => $this->legacyMeta($paginator)];
    }

    /** Ported from loadInternalNonConformities(). */
    public function legacyNonConformitiesByVessel(string $vesselId, ?string $from, ?string $to, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_nonconformities')
            ->join('tb_internal_audit_report', 'tb_internal_audit_report.audit_ref', '=', 'tb_nonconformities.source_of_nc_ref_no')
            ->where('tb_internal_audit_report.vesID', $vesselId)
            ->where('tb_internal_audit_report.is_deleted', '0')->where('tb_nonconformities.is_inactive', '0')
            ->select(['tb_nonconformities.ncID', 'tb_nonconformities.ncr_no', 'tb_nonconformities.date_of_nc', 'tb_nonconformities.source_of_nc_ref_no', 'tb_nonconformities.description', 'tb_nonconformities.root_cause', 'tb_nonconformities.corrective_action', 'tb_nonconformities.verification']);
        $this->legacyScopeDateRange($builder, $from, $to, 'tb_internal_audit_report.this_date');

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

    private function legacyScopeDateRange(QueryBuilder $builder, ?string $from, ?string $to, string $column = 'this_date'): QueryBuilder
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
