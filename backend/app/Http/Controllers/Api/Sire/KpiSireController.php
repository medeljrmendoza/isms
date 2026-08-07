<?php

namespace App\Http\Controllers\Api\Sire;

use App\Http\Controllers\Controller;
use App\Repositories\Sire\KpiSireRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Kpi_sire.php. Read-only reporting layer, no
 * writes. Not ported: the three Observations-based charts — see
 * KpiSireRepository's docblock. Also not ported: the tb_logs
 * audit-trail writes on each chart view (no audit log exists in this
 * migration, same as every other module).
 */
class KpiSireController extends Controller
{
    public function __construct(private readonly KpiSireRepository $kpi) {}

    /**
     * GET /api/kpi/sire/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['vessels' => $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id)],
        ]);
    }

    /**
     * GET /api/kpi/sire/summary?from=&to=
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $rows = $this->kpi->legacyReportsPerVessel($from, $to, $request->user()?->legacy_user_id);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/sire/reports-by-vessel?vessel_id=&from=&to=
     */
    public function reportsByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyReportsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiSireRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }
}
