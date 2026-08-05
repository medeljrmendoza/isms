<?php

namespace App\Http\Controllers\Api\NonSire;

use App\Http\Controllers\Controller;
use App\Models\NonSire\NonSireReport;
use App\Repositories\NonSire\KpiNonSireRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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
            'data' => [
                'vessels' => LegacyDb::isConfigured()
                    ? $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id)
                    : $this->kpi->vesselOptions(),
            ],
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

        if (LegacyDb::isConfigured()) {
            $legacyUserId = $request->user()?->legacy_user_id;
            $rows = $isType
                ? $this->kpi->legacyReportsPerInspectionType($from, $to, $legacyUserId)
                : $this->kpi->legacyReportsPerVessel($from, $to, $legacyUserId);

            return response()->json(['data' => $rows]);
        }

        $rows = $isType
            ? $this->kpi->reportsPerInspectionType($from, $to)
            : $this->kpi->reportsPerVessel($from, $to);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/non-sire/reports-by-vessel?vessel_id=&from=&to=
     */
    public function reportsByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyReportsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiNonSireRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->reportsByVessel(
            (int) $request->query('vessel_id'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiNonSireRepository::reportColumns(),
                'rows' => collect($paginator->items())->map(fn (NonSireReport $r) => [
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

    /**
     * GET /api/kpi/non-sire/reports-by-inspection-type?inspection_type=&from=&to=
     */
    public function reportsByInspectionType(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyReportsByInspectionType((string) $request->query('inspection_type'), $from, $to, TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => KpiNonSireRepository::inspectionTypeReportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->reportsByInspectionType(
            (string) $request->query('inspection_type'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiNonSireRepository::inspectionTypeReportColumns(),
                'rows' => collect($paginator->items())->map(fn (NonSireReport $r) => [
                    'id' => $r->id,
                    'vessel' => $r->vessel?->display_name ?? '',
                    'added_by' => $r->added_by,
                    'dateof_inspection' => $r->dateof_inspection?->format('Y-m-d'),
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
