<?php

namespace App\Http\Controllers\Api\PscReports;

use App\Http\Controllers\Controller;
use App\Repositories\PscReports\KpiPscInspectionsRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Kpi_psc_inspections.php. Read-only reporting
 * layer, no writes. Not ported: the "Observations per Vessel" chart —
 * see KpiPscInspectionsRepository's docblock. Also not ported: the
 * tb_logs audit-trail writes on each chart view (no audit log exists in
 * this migration, same as every other module).
 */
class KpiPscInspectionsController extends Controller
{
    public function __construct(private readonly KpiPscInspectionsRepository $kpi) {}

    /**
     * GET /api/kpi/psc-inspections/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id),
                'mous' => $this->kpi->legacyMouOptions(),
            ],
        ]);
    }

    /**
     * GET /api/kpi/psc-inspections/summary?filter=vessel|mou|nonconformities&from=&to=
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;
        $filter = $request->query('filter');
        $legacyUserId = $request->user()?->legacy_user_id;

        $rows = match ($filter) {
            'mou' => $this->kpi->legacyReportsPerMou($from, $to, $legacyUserId),
            'nonconformities' => $this->kpi->legacyNonConformitiesPerVessel($from, $to, $legacyUserId),
            default => $this->kpi->legacyReportsPerVessel($from, $to, $legacyUserId),
        };

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/psc-inspections/reports-by-vessel?vessel_id=&from=&to=
     */
    public function reportsByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyReportsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiPscInspectionsRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/kpi/psc-inspections/reports-by-mou?mou_id=&from=&to=
     */
    public function reportsByMou(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyReportsByMou((string) $request->query('mou_id'), $from, $to, TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => KpiPscInspectionsRepository::mouReportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/kpi/psc-inspections/nonconformities-by-vessel?vessel_id=&from=&to=
     */
    public function nonConformitiesByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyNonConformitiesByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiPscInspectionsRepository::nonconformityColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }
}
