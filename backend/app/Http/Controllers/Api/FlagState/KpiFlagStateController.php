<?php

namespace App\Http\Controllers\Api\FlagState;

use App\Http\Controllers\Controller;
use App\Models\FlagState\FlagStateReport;
use App\Models\Nonconformities\Nonconformity;
use App\Repositories\FlagState\KpiFlagStateRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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
            'data' => [
                'vessels' => LegacyDb::isConfigured()
                    ? $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id)
                    : $this->kpi->vesselOptions(),
            ],
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

        if (LegacyDb::isConfigured()) {
            $legacyUserId = $request->user()?->legacy_user_id;
            $rows = $isNc
                ? $this->kpi->legacyNonConformitiesPerVessel($from, $to, $legacyUserId)
                : $this->kpi->legacyReportsPerVessel($from, $to, $legacyUserId);

            return response()->json(['data' => $rows]);
        }

        $rows = $isNc
            ? $this->kpi->nonConformitiesPerVessel($from, $to)
            : $this->kpi->reportsPerVessel($from, $to);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/flag-state/reports-by-vessel?vessel_id=&from=&to=
     */
    public function reportsByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyReportsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiFlagStateRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->reportsByVessel(
            (int) $request->query('vessel_id'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiFlagStateRepository::reportColumns(),
                'rows' => collect($paginator->items())->map(fn (FlagStateReport $r) => [
                    'id' => $r->id,
                    'ref_no' => $r->ref_no,
                    'dateof_inspection' => $r->dateof_inspection?->format('Y-m-d'),
                    'placeof_inspection' => $r->placeof_inspection,
                    'inspector' => $r->inspector,
                ])->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/kpi/flag-state/nonconformities-by-vessel?vessel_id=&from=&to=
     */
    public function nonConformitiesByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyNonConformitiesByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiFlagStateRepository::nonconformityColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->nonConformitiesByVessel(
            (int) $request->query('vessel_id'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiFlagStateRepository::nonconformityColumns(),
                'rows' => collect($paginator->items())->map(fn (Nonconformity $n) => [
                    'id' => $n->id,
                    'ncr_no' => $n->ncr_no,
                    'date_of_nc' => $n->date_of_nc?->format('Y-m-d'),
                    'source_of_nc_ref_no' => $n->source_of_nc_ref_no,
                    'description' => $n->description,
                    'root_cause' => $n->root_cause,
                    'corrective_action' => $n->corrective_action,
                    'verification' => $n->verification,
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
