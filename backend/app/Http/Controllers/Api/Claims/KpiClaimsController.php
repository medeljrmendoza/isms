<?php

namespace App\Http\Controllers\Api\Claims;

use App\Http\Controllers\Controller;
use App\Models\Claims\Claim;
use App\Repositories\Claims\KpiClaimsRepository;
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
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => ['vessels' => $this->kpi->vesselOptions()],
        ]);
    }

    /**
     * GET /api/kpi/claims/summary?filter=vessel|category&from=&to=
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $rows = $request->query('filter') === 'category'
            ? $this->kpi->claimsPerCategory($from, $to)
            : $this->kpi->claimsPerVessel($from, $to);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/claims/by-vessel?vessel_id=&from=&to=
     */
    public function byVessel(Request $request): JsonResponse
    {
        $paginator = $this->kpi->claimsByVessel(
            (int) $request->query('vessel_id'),
            $request->query('from') ?: null,
            $request->query('to') ?: null,
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
        $paginator = $this->kpi->claimsByCategory(
            (string) $request->query('category'),
            $request->query('from') ?: null,
            $request->query('to') ?: null,
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
