<?php

namespace App\Http\Controllers\Api\CompanyInspections;

use App\Http\Controllers\Controller;
use App\Models\CompanyInspections\AuditReport;
use App\Models\Nonconformities\Nonconformity;
use App\Repositories\CompanyInspections\KpiCompanyInspectionsRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Kpi_company_inspections.php. Read-only
 * reporting layer, no writes. Not ported: the two Observations-based
 * charts — see KpiCompanyInspectionsRepository's docblock. Also not
 * ported: the tb_logs audit-trail writes on each chart view (no audit
 * log exists in this migration, same as every other module).
 */
class KpiCompanyInspectionsController extends Controller
{
    public function __construct(private readonly KpiCompanyInspectionsRepository $kpi) {}

    /**
     * GET /api/kpi/company-inspections/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => LegacyDb::isConfigured()
                    ? $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id)
                    : $this->kpi->vesselOptions(),
            ],
        ]);
    }

    /**
     * GET /api/kpi/company-inspections/summary?filter=vessel|company|nc_vessel|nc_company&from=&to=
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;
        $filter = $request->query('filter');

        if (LegacyDb::isConfigured()) {
            $legacyUserId = $request->user()?->legacy_user_id;
            $rows = match ($filter) {
                'company' => $this->kpi->legacyReportsPerCompany($from, $to),
                'nc_vessel' => $this->kpi->legacyNonConformitiesPerVessel($from, $to, $legacyUserId),
                'nc_company' => $this->kpi->legacyNonConformitiesPerCompany($from, $to),
                default => $this->kpi->legacyReportsPerVessel($from, $to, $legacyUserId),
            };

            return response()->json(['data' => $rows]);
        }

        $rows = match ($filter) {
            'company' => $this->kpi->reportsPerCompany($from, $to),
            'nc_vessel' => $this->kpi->nonConformitiesPerVessel($from, $to),
            'nc_company' => $this->kpi->nonConformitiesPerCompany($from, $to),
            default => $this->kpi->reportsPerVessel($from, $to),
        };

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/company-inspections/reports-by-vessel?vessel_id=&from=&to=
     */
    public function reportsByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyReportsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiCompanyInspectionsRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->reportsByVessel(
            (int) $request->query('vessel_id'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiCompanyInspectionsRepository::reportColumns(),
                'rows' => collect($paginator->items())->map(fn (AuditReport $r) => $this->mapReport($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/kpi/company-inspections/reports-by-company?company=&from=&to=
     */
    public function reportsByCompany(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyReportsByCompany((string) $request->query('company'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiCompanyInspectionsRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->reportsByCompany(
            (string) $request->query('company'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiCompanyInspectionsRepository::reportColumns(),
                'rows' => collect($paginator->items())->map(fn (AuditReport $r) => $this->mapReport($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/kpi/company-inspections/nonconformities-by-vessel?vessel_id=&from=&to=
     */
    public function nonConformitiesByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyNonConformitiesByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiCompanyInspectionsRepository::nonconformityColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->nonConformitiesByVessel(
            (int) $request->query('vessel_id'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiCompanyInspectionsRepository::nonconformityColumns(),
                'rows' => collect($paginator->items())->map(fn (Nonconformity $n) => $this->mapNonconformity($n))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/kpi/company-inspections/nonconformities-by-company?company=&from=&to=
     */
    public function nonConformitiesByCompany(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyNonConformitiesByCompany((string) $request->query('company'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiCompanyInspectionsRepository::nonconformityColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->nonConformitiesByCompany(
            (string) $request->query('company'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiCompanyInspectionsRepository::nonconformityColumns(),
                'rows' => collect($paginator->items())->map(fn (Nonconformity $n) => $this->mapNonconformity($n))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    private function mapReport(AuditReport $r): array
    {
        return [
            'id' => $r->id,
            'audit_ref' => $r->audit_ref,
            'this_date' => $r->this_date?->format('Y-m-d'),
            'placeof_audit' => $r->placeof_audit,
            'audit_type' => $r->auditType?->name ?? '',
            'audit_kind' => $r->auditKind?->name ?? '',
        ];
    }

    private function mapNonconformity(Nonconformity $n): array
    {
        return [
            'id' => $n->id,
            'ncr_no' => $n->ncr_no,
            'date_of_nc' => $n->date_of_nc?->format('Y-m-d'),
            'source_of_nc_ref_no' => $n->source_of_nc_ref_no,
            'description' => $n->description,
            'root_cause' => $n->root_cause,
            'corrective_action' => $n->corrective_action,
            'verification' => $n->verification,
        ];
    }

    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
