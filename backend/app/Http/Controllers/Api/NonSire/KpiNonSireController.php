<?php

namespace App\Http\Controllers\Api\NonSire;

use App\Http\Controllers\Controller;
use App\Repositories\NonSire\KpiNonSireRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Kpi_non_sire.php. Read-only reporting layer,
 * no writes. Not ported: the two Observations-based charts — see
 * KpiNonSireRepository's docblock. Also not ported: the tb_logs
 * audit-trail writes on each chart view (no audit log exists in this
 * migration, same as every other module).
 */
class KpiNonSireController extends Controller
{
    public function __construct(private readonly KpiNonSireRepository $kpi) {}

    /**
     * GET /api/kpi/non-sire/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['vessels' => $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id)],
        ]);
    }

    /**
     * GET /api/kpi/non-sire/summary?filter=vessel|inspection_type&from=&to=
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;
        $isType = $request->query('filter') === 'inspection_type';
        $legacyUserId = $request->user()?->legacy_user_id;

        $rows = $isType
            ? $this->kpi->legacyReportsPerInspectionType($from, $to, $legacyUserId)
            : $this->kpi->legacyReportsPerVessel($from, $to, $legacyUserId);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/non-sire/reports-by-vessel?vessel_id=&from=&to=
     */
    public function reportsByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyReportsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiNonSireRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/kpi/non-sire/reports-by-inspection-type?inspection_type=&from=&to=
     */
    public function reportsByInspectionType(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyReportsByInspectionType((string) $request->query('inspection_type'), $from, $to, TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => KpiNonSireRepository::inspectionTypeReportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }
}
