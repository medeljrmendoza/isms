<?php

namespace App\Http\Controllers\Api\Sire;

use App\Http\Controllers\Controller;
use App\Models\Sire\SireReport;
use App\Repositories\Sire\KpiSireRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => ['vessels' => $this->kpi->vesselOptions()],
        ]);
    }

    /**
     * GET /api/kpi/sire/summary?from=&to=
     */
    public function summary(Request $request): JsonResponse
    {
        $rows = $this->kpi->reportsPerVessel($request->query('from') ?: null, $request->query('to') ?: null);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/sire/reports-by-vessel?vessel_id=&from=&to=
     */
    public function reportsByVessel(Request $request): JsonResponse
    {
        $paginator = $this->kpi->reportsByVessel(
            (int) $request->query('vessel_id'),
            $request->query('from') ?: null,
            $request->query('to') ?: null,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiSireRepository::reportColumns(),
                'rows' => collect($paginator->items())->map(fn (SireReport $r) => [
                    'id' => $r->id,
                    'dateof_inspection' => $r->dateof_inspection?->format('Y-m-d'),
                    'added_by' => $r->added_by,
                    'placeof_inspection' => $r->placeof_inspection,
                    'company_name' => $r->company_name,
                    'inspector_name' => $r->inspector_name,
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
