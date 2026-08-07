<?php

namespace App\Http\Controllers\Api\Claims;

use App\Http\Controllers\Controller;
use App\Repositories\Claims\KpiClaimsRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Kpi_claims.php. Read-only reporting layer, no
 * writes. Not ported: the tb_logs audit-trail writes on each chart view
 * (no audit log exists in this migration, same as every other module).
 */
class KpiClaimsController extends Controller
{
    public function __construct(private readonly KpiClaimsRepository $kpi) {}

    /**
     * GET /api/kpi/claims/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['vessels' => $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id)],
        ]);
    }

    /**
     * GET /api/kpi/claims/summary?filter=vessel|category&from=&to=
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;
        $isCategory = $request->query('filter') === 'category';
        $legacyUserId = $request->user()?->legacy_user_id;

        $rows = $isCategory
            ? $this->kpi->legacyClaimsPerCategory($from, $to, $legacyUserId)
            : $this->kpi->legacyClaimsPerVessel($from, $to, $legacyUserId);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/claims/by-vessel?vessel_id=&from=&to=
     */
    public function byVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyClaimsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiClaimsRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/kpi/claims/by-category?category=&from=&to=
     */
    public function byCategory(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $result = $this->kpi->legacyClaimsByCategory((string) $request->query('category'), $from, $to, TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => KpiClaimsRepository::categoryColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }
}
