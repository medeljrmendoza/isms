<?php

namespace App\Http\Controllers\Api\FlagState;

use App\Http\Controllers\Controller;
use App\Repositories\FlagState\KpiFlagStateRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Kpi_flag_state.php. Read-only reporting
 * layer, no writes. Not ported: the "Observations per Vessel" chart —
 * see KpiFlagStateRepository's docblock. Also not ported: the tb_logs
 * audit-trail writes on each chart view (no audit log exists in this
 * migration, same as every other module).
 */
class KpiFlagStateController extends Controller
{
    public function __construct(private readonly KpiFlagStateRepository $kpi) {}

    /**
     * GET /api/kpi/flag-state/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['vessels' => $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id)],
        ]);
    }

    /**
     * GET /api/kpi/flag-state/summary?filter=vessel|nonconformities&from=&to=
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;
        $isNc = $request->query('filter') === 'nonconformities';
        $legacyUserId = $request->user()?->legacy_user_id;

        $rows = $isNc
            ? $this->kpi->legacyNonConformitiesPerVessel($from, $to, $legacyUserId)
            : $this->kpi->legacyReportsPerVessel($from, $to, $legacyUserId);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/flag-state/reports-by-vessel?vessel_id=&from=&to=
     */
    public function reportsByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyReportsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiFlagStateRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/kpi/flag-state/nonconformities-by-vessel?vessel_id=&from=&to=
     */
    public function nonConformitiesByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyNonConformitiesByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiFlagStateRepository::nonconformityColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }
}
