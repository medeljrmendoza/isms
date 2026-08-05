<?php

namespace App\Http\Controllers\Api\Sire;

use App\Http\Controllers\Controller;
use App\Models\Sire\SireReport;
use App\Repositories\Sire\KpiSireRepository;
use App\Support\LegacyDb;
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
     * GET /api/kpi/sire/summary?from=&to=
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $rows = LegacyDb::isConfigured()
            ? $this->kpi->legacyReportsPerVessel($from, $to, $request->user()?->legacy_user_id)
            : $this->kpi->reportsPerVessel($from, $to);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/sire/reports-by-vessel?vessel_id=&from=&to=
     */
    public function reportsByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyReportsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiSireRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->reportsByVessel(
            (int) $request->query('vessel_id'),
            $from,
            $to,
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
