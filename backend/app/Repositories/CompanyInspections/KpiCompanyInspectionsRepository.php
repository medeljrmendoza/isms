<?php

namespace App\Repositories\CompanyInspections;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Kpi_company_inspections.php. Four of the six
 * legacy filters are portable: Reports per Vessel, Reports per Company,
 * Non Conformities per Vessel, Non Conformities per Company.
 * Observations per Vessel and Observations per Company are dropped — no
 * Observations module exists anywhere in this migration.
 */
class KpiCompanyInspectionsRepository
{
    private const REPORT_COLUMNS = [
        ['key' => 'audit_ref', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'this_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_audit', 'label' => 'PLACE OF AUDIT', 'sortable' => false],
        ['key' => 'audit_type', 'label' => 'AUDIT TYPE', 'sortable' => false],
        ['key' => 'audit_kind', 'label' => 'AUDIT KIND', 'sortable' => false],
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

    /**
     * Ported from Kpi_company_inspections::index()'s $vessels query:
     * ACTIVE vessels the given legacy user is assigned to, sorted by name.
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

    /** Ported from the $companies query: distinct COMPANY-type audit companies, unscoped by vessel/user. */
    public function legacyCompanyNames(): array
    {
        return DB::connection('legacy')->table('tb_audit_report')
            ->where('vessel_company', 'COMPANY')->where('is_deleted', '0')
            ->whereNotNull('company')->distinct()->orderBy('company')->pluck('company')->all();
    }

    /** Ported from index()'s filter==0 branch, reading tb_audit_report directly. */
    public function legacyReportsPerVessel(?string $from, ?string $to, ?string $legacyUserId): array
    {
        return collect($this->legacyVesselOptions($legacyUserId))
            ->map(function (array $v) use ($from, $to) {
                $q = DB::connection('legacy')->table('tb_audit_report')
                    ->where('vesID', $v['id'])->where('is_deleted', '0');
                $this->legacyScopeDateRange($q, $from, $to);

                return ['label' => $v['label'], 'count' => $q->count()];
            })->all();
    }

    /** Ported from index()'s filter==1 branch — no vessel/user scoping in legacy either. */
    public function legacyReportsPerCompany(?string $from, ?string $to): array
    {
        return collect($this->legacyCompanyNames())->map(function (string $company) use ($from, $to) {
            $q = DB::connection('legacy')->table('tb_audit_report')
                ->where('company', $company)->where('is_deleted', '0');
            $this->legacyScopeDateRange($q, $from, $to);

            return ['label' => $company, 'count' => $q->count()];
        })->all();
    }

    /** Ported from index()'s filter==2 branch. */
    public function legacyNonConformitiesPerVessel(?string $from, ?string $to, ?string $legacyUserId): array
    {
        return collect($this->legacyVesselOptions($legacyUserId))
            ->map(function (array $v) use ($from, $to) {
                $q = DB::connection('legacy')->table('tb_nonconformities')
                    ->join('tb_audit_report', 'tb_audit_report.audit_ref', '=', 'tb_nonconformities.source_of_nc_ref_no')
                    ->where('tb_audit_report.vesID', $v['id'])
                    ->where('tb_nonconformities.is_inactive', '0')
                    ->where('tb_audit_report.is_deleted', '0');
                $this->legacyScopeDateRange($q, $from, $to, 'tb_audit_report.this_date');

                return ['label' => $v['label'], 'count' => $q->count()];
            })->all();
    }

    /** Ported from index()'s filter==3 branch — no vessel/user scoping in legacy either. */
    public function legacyNonConformitiesPerCompany(?string $from, ?string $to): array
    {
        return collect($this->legacyCompanyNames())->map(function (string $company) use ($from, $to) {
            $q = DB::connection('legacy')->table('tb_nonconformities')
                ->join('tb_audit_report', 'tb_audit_report.audit_ref', '=', 'tb_nonconformities.source_of_nc_ref_no')
                ->where('tb_audit_report.company', $company)
                ->where('tb_nonconformities.is_inactive', '0')
                ->where('tb_audit_report.is_deleted', '0');
            $this->legacyScopeDateRange($q, $from, $to, 'tb_audit_report.this_date');

            return ['label' => $company, 'count' => $q->count()];
        })->all();
    }

    /** Ported from loadCompanyReportsVesselData(). */
    public function legacyReportsByVessel(string $vesselId, ?string $from, ?string $to, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_audit_report')
            ->leftJoin('pl_audit_kinds', 'pl_audit_kinds.auditKindID', '=', 'tb_audit_report.audit_kind')
            ->leftJoin('pl_audit_types', 'pl_audit_types.auditTypeID', '=', 'tb_audit_report.audit_type')
            ->where('tb_audit_report.vesID', $vesselId)->where('tb_audit_report.is_deleted', '0')
            ->select(['tb_audit_report.auditID', 'tb_audit_report.audit_ref', 'tb_audit_report.this_date', 'tb_audit_report.placeof_audit', 'pl_audit_types.audit_type_name', 'pl_audit_kinds.audit_kind_name']);
        $this->legacyScopeDateRange($builder, $from, $to, 'tb_audit_report.this_date');

        return $this->legacyPaginateReports($builder, $query);
    }

    /** Ported from loadCompanyReportsCompanyData() — no vessel/user scoping in legacy either. */
    public function legacyReportsByCompany(string $company, ?string $from, ?string $to, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_audit_report')
            ->leftJoin('pl_audit_kinds', 'pl_audit_kinds.auditKindID', '=', 'tb_audit_report.audit_kind')
            ->leftJoin('pl_audit_types', 'pl_audit_types.auditTypeID', '=', 'tb_audit_report.audit_type')
            ->where('tb_audit_report.company', $company)
            ->select(['tb_audit_report.auditID', 'tb_audit_report.audit_ref', 'tb_audit_report.this_date', 'tb_audit_report.placeof_audit', 'pl_audit_types.audit_type_name', 'pl_audit_kinds.audit_kind_name']);
        $this->legacyScopeDateRange($builder, $from, $to, 'tb_audit_report.this_date');

        return $this->legacyPaginateReports($builder, $query);
    }

    /** Ported from loadNonConformitiesPerVessel(). */
    public function legacyNonConformitiesByVessel(string $vesselId, ?string $from, ?string $to, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_nonconformities')
            ->join('tb_audit_report', 'tb_audit_report.audit_ref', '=', 'tb_nonconformities.source_of_nc_ref_no')
            ->where('tb_audit_report.vesID', $vesselId)
            ->where('tb_audit_report.is_deleted', '0')->where('tb_nonconformities.is_inactive', '0')
            ->select($this->legacyNcColumns());
        $this->legacyScopeDateRange($builder, $from, $to, 'tb_audit_report.this_date');

        return $this->legacyPaginateNonconformities($builder, $query);
    }

    /**
     * Ported from loadNonConformitiesPerCompany() — unlike every sibling
     * NC drill-down in this migration, legacy's own query here has no
     * is_deleted/is_inactive filter at all, so this intentionally omits
     * them too (a genuine legacy inconsistency vs. its own summary count,
     * not a simplification).
     */
    public function legacyNonConformitiesByCompany(string $company, ?string $from, ?string $to, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_nonconformities')
            ->join('tb_audit_report', 'tb_audit_report.audit_ref', '=', 'tb_nonconformities.source_of_nc_ref_no')
            ->where('tb_audit_report.company', $company)
            ->select($this->legacyNcColumns());
        $this->legacyScopeDateRange($builder, $from, $to, 'tb_audit_report.this_date');

        return $this->legacyPaginateNonconformities($builder, $query);
    }

    private function legacyNcColumns(): array
    {
        return ['tb_nonconformities.ncID', 'tb_nonconformities.ncr_no', 'tb_nonconformities.date_of_nc', 'tb_nonconformities.source_of_nc_ref_no', 'tb_nonconformities.description', 'tb_nonconformities.root_cause', 'tb_nonconformities.corrective_action', 'tb_nonconformities.verification'];
    }

    private function legacyPaginateReports(QueryBuilder $builder, TableQuery $query): array
    {
        $sortMap = ['audit_ref' => 'tb_audit_report.audit_ref', 'this_date' => 'tb_audit_report.this_date'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_audit_report.this_date';
        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->auditID,
            'audit_ref' => $r->audit_ref,
            'this_date' => $r->this_date,
            'placeof_audit' => $r->placeof_audit,
            'audit_type' => $r->audit_type_name ?? '',
            'audit_kind' => $r->audit_kind_name ?? '',
        ])->all();

        return ['rows' => $rows, 'meta' => $this->legacyMeta($paginator)];
    }

    private function legacyPaginateNonconformities(QueryBuilder $builder, TableQuery $query): array
    {
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
