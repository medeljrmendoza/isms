<?php

namespace App\Http\Controllers\Api\InternalAudits;

use App\Http\Controllers\Controller;
use App\Repositories\InternalAudits\KpiInternalAuditsRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Kpi_internal.php. Read-only reporting layer,
 * no writes. Not ported: the "Observations per Vessel" chart — see
 * KpiInternalAuditsRepository's docblock. Also not ported: the tb_logs
 * audit-trail writes on each chart view (no audit log exists in this
 * migration, same as every other module).
 */
class KpiInternalAuditsController extends Controller
{
    public function __construct(private readonly KpiInternalAuditsRepository $kpi) {}

    /**
     * GET /api/kpi/internal-audits/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['vessels' => $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id)],
        ]);
    }

    /**
     * GET /api/kpi/internal-audits/summary?filter=vessel|nonconformities&from=&to=
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
     * GET /api/kpi/internal-audits/reports-by-vessel?vessel_id=&from=&to=
     */
    public function reportsByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyReportsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiInternalAuditsRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/kpi/internal-audits/nonconformities-by-vessel?vessel_id=&from=&to=
     */
    public function nonConformitiesByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyNonConformitiesByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiInternalAuditsRepository::nonconformityColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }
}
