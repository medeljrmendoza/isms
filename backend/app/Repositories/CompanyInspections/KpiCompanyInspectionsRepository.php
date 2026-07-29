<?php

namespace App\Repositories\CompanyInspections;

use App\Models\CompanyInspections\AuditReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Ported from Controllers/Kpi_company_inspections.php. Four of the six
 * legacy filters are portable: Reports per Vessel, Reports per Company,
 * Non Conformities per Vessel, Non Conformities per Company.
 * Observations per Vessel and Observations per Company are dropped — no
 * Observations module exists anywhere in this migration. "Company" is
 * grouped by the free-text AuditReport.company column scoped to
 * vessel_company = 'COMPANY' rows, matching legacy's distinct-company
 * query exactly.
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

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    private function companyNames(): array
    {
        return AuditReport::query()
            ->where('vessel_company', 'COMPANY')
            ->where('is_deleted', false)
            ->whereNotNull('company')
            ->distinct()
            ->orderBy('company')
            ->pluck('company')
            ->all();
    }

    /** Ported from index()'s filter==0 branch. */
    public function reportsPerVessel(?string $from, ?string $to): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => [
                'label' => $v->display_name,
                'count' => $this->scopeDateRange(AuditReport::query()->where('vessel_id', $v->id)->where('is_deleted', false), $from, $to)->count(),
            ])
            ->all();
    }

    /** Ported from index()'s filter==1 branch. */
    public function reportsPerCompany(?string $from, ?string $to): array
    {
        return collect($this->companyNames())->map(fn (string $company) => [
            'label' => $company,
            'count' => $this->scopeDateRange(AuditReport::query()->where('company', $company)->where('is_deleted', false), $from, $to)->count(),
        ])->all();
    }

    /** Ported from index()'s filter==2 branch. */
    public function nonConformitiesPerVessel(?string $from, ?string $to): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => [
                'label' => $v->display_name,
                'count' => Nonconformity::query()
                    ->where('is_inactive', false)
                    ->whereHas('auditReport', function (Builder $q) use ($v, $from, $to) {
                        $q->where('vessel_id', $v->id)->where('is_deleted', false);
                        $this->scopeDateRange($q, $from, $to, 'this_date');
                    })
                    ->count(),
            ])
            ->all();
    }

    /** Ported from index()'s filter==3 branch. */
    public function nonConformitiesPerCompany(?string $from, ?string $to): array
    {
        return collect($this->companyNames())->map(fn (string $company) => [
            'label' => $company,
            'count' => Nonconformity::query()
                ->where('is_inactive', false)
                ->whereHas('auditReport', function (Builder $q) use ($company, $from, $to) {
                    $q->where('company', $company)->where('is_deleted', false);
                    $this->scopeDateRange($q, $from, $to, 'this_date');
                })
                ->count(),
        ])->all();
    }

    /** Ported from loadCompanyReportsVesselData(). */
    public function reportsByVessel(int $vesselId, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->scopeDateRange(
            AuditReport::query()->with(['auditType', 'auditKind'])->where('vessel_id', $vesselId)->where('is_deleted', false),
            $from,
            $to,
            'this_date',
        );

        return $this->paginateReports($builder, $query);
    }

    /** Ported from loadCompanyReportsCompanyData(). */
    public function reportsByCompany(string $company, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->scopeDateRange(
            AuditReport::query()->with(['auditType', 'auditKind'])->where('company', $company)->where('is_deleted', false),
            $from,
            $to,
            'this_date',
        );

        return $this->paginateReports($builder, $query);
    }

    /** Ported from loadNonConformitiesPerVessel(). */
    public function nonConformitiesByVessel(int $vesselId, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = Nonconformity::query()
            ->where('is_inactive', false)
            ->whereHas('auditReport', function (Builder $q) use ($vesselId, $from, $to) {
                $q->where('vessel_id', $vesselId)->where('is_deleted', false);
                $this->scopeDateRange($q, $from, $to, 'this_date');
            });

        return $this->paginateNonconformities($builder, $query);
    }

    /** Ported from loadNonConformitiesPerCompany(). */
    public function nonConformitiesByCompany(string $company, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = Nonconformity::query()
            ->where('is_inactive', false)
            ->whereHas('auditReport', function (Builder $q) use ($company, $from, $to) {
                $q->where('company', $company)->where('is_deleted', false);
                $this->scopeDateRange($q, $from, $to, 'this_date');
            });

        return $this->paginateNonconformities($builder, $query);
    }

    private function paginateReports(Builder $builder, TableQuery $query): LengthAwarePaginator
    {
        $sortable = array_column(array_filter(self::REPORT_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'this_date';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    private function paginateNonconformities(Builder $builder, TableQuery $query): LengthAwarePaginator
    {
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
}
