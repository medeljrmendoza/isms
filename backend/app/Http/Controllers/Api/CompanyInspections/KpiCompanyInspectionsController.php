<?php

namespace App\Http\Controllers\Api\CompanyInspections;

use App\Http\Controllers\Controller;
use App\Repositories\CompanyInspections\KpiCompanyInspectionsRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'data' => ['vessels' => $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id)],
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
        $legacyUserId = $request->user()?->legacy_user_id;

        $rows = match ($filter) {
            'company' => $this->kpi->legacyReportsPerCompany($from, $to),
            'nc_vessel' => $this->kpi->legacyNonConformitiesPerVessel($from, $to, $legacyUserId),
            'nc_company' => $this->kpi->legacyNonConformitiesPerCompany($from, $to),
            default => $this->kpi->legacyReportsPerVessel($from, $to, $legacyUserId),
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

        $result = $this->kpi->legacyReportsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiCompanyInspectionsRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/kpi/company-inspections/reports-by-company?company=&from=&to=
     */
    public function reportsByCompany(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyReportsByCompany((string) $request->query('company'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiCompanyInspectionsRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/kpi/company-inspections/nonconformities-by-vessel?vessel_id=&from=&to=
     */
    public function nonConformitiesByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyNonConformitiesByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiCompanyInspectionsRepository::nonconformityColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/kpi/company-inspections/nonconformities-by-company?company=&from=&to=
     */
    public function nonConformitiesByCompany(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyNonConformitiesByCompany((string) $request->query('company'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiCompanyInspectionsRepository::nonconformityColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }
}
