<?php

namespace App\Http\Controllers\Api\Claims;

use App\Http\Controllers\Controller;
use App\Models\Claims\Claim;
use App\Repositories\Claims\KpiClaimsRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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
            'data' => [
                'vessels' => LegacyDb::isConfigured()
                    ? $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id)
                    : $this->kpi->vesselOptions(),
            ],
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

        if (LegacyDb::isConfigured()) {
            $legacyUserId = $request->user()?->legacy_user_id;
            $rows = $isCategory
                ? $this->kpi->legacyClaimsPerCategory($from, $to, $legacyUserId)
                : $this->kpi->legacyClaimsPerVessel($from, $to, $legacyUserId);

            return response()->json(['data' => $rows]);
        }

        $rows = $isCategory
            ? $this->kpi->claimsPerCategory($from, $to)
            : $this->kpi->claimsPerVessel($from, $to);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/claims/by-vessel?vessel_id=&from=&to=
     */
    public function byVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyClaimsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiClaimsRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->claimsByVessel(
            (int) $request->query('vessel_id'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiClaimsRepository::reportColumns(),
                'rows' => collect($paginator->items())->map(fn (Claim $c) => [
                    'id' => $c->id,
                    'claim_no' => $c->claim_no,
                    'claims_category' => $c->claims_category,
                    'report_date' => $c->report_date?->format('Y-m-d'),
                    'nature_diagnosis' => $c->nature_diagnosis,
                    'status' => $c->status,
                    'amount_usd' => $c->amount_usd,
                ])->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/kpi/claims/by-category?category=&from=&to=
     */
    public function byCategory(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyClaimsByCategory((string) $request->query('category'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiClaimsRepository::categoryColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->claimsByCategory(
            (string) $request->query('category'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiClaimsRepository::categoryColumns(),
                'rows' => collect($paginator->items())->map(fn (Claim $c) => [
                    'id' => $c->id,
                    'claim_no' => $c->claim_no,
                    'vessel' => $c->vessel?->display_name ?? '',
                    'report_date' => $c->report_date?->format('Y-m-d'),
                    'nature_diagnosis' => $c->nature_diagnosis,
                    'status' => $c->status,
                    'amount_usd' => $c->amount_usd,
                ])->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
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
