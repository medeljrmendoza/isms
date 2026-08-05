<?php

namespace App\Http\Controllers\Api\PscReports;

use App\Http\Controllers\Controller;
use App\Models\Nonconformities\Nonconformity;
use App\Models\PscReports\PscReport;
use App\Repositories\PscReports\KpiPscInspectionsRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Kpi_psc_inspections.php. Read-only reporting
 * layer, no writes. Not ported: the "Observations per Vessel" chart —
 * see KpiPscInspectionsRepository's docblock. Also not ported: the
 * tb_logs audit-trail writes on each chart view (no audit log exists in
 * this migration, same as every other module).
 */
class KpiPscInspectionsController extends Controller
{
    public function __construct(private readonly KpiPscInspectionsRepository $kpi) {}

    /**
     * GET /api/kpi/psc-inspections/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => LegacyDb::isConfigured() ? [
                'vessels' => $this->kpi->legacyVesselOptions($request->user()?->legacy_user_id),
                'mous' => $this->kpi->legacyMouOptions(),
            ] : [
                'vessels' => $this->kpi->vesselOptions(),
                'mous' => $this->kpi->mouOptions(),
            ],
        ]);
    }

    /**
     * GET /api/kpi/psc-inspections/summary?filter=vessel|mou|nonconformities&from=&to=
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;
        $filter = $request->query('filter');

        if (LegacyDb::isConfigured()) {
            $legacyUserId = $request->user()?->legacy_user_id;
            $rows = match ($filter) {
                'mou' => $this->kpi->legacyReportsPerMou($from, $to, $legacyUserId),
                'nonconformities' => $this->kpi->legacyNonConformitiesPerVessel($from, $to, $legacyUserId),
                default => $this->kpi->legacyReportsPerVessel($from, $to, $legacyUserId),
            };

            return response()->json(['data' => $rows]);
        }

        $rows = match ($filter) {
            'mou' => $this->kpi->reportsPerMou($from, $to),
            'nonconformities' => $this->kpi->nonConformitiesPerVessel($from, $to),
            default => $this->kpi->reportsPerVessel($from, $to),
        };

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/kpi/psc-inspections/reports-by-vessel?vessel_id=&from=&to=
     */
    public function reportsByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyReportsByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiPscInspectionsRepository::reportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->reportsByVessel(
            (int) $request->query('vessel_id'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiPscInspectionsRepository::reportColumns(),
                'rows' => collect($paginator->items())->map(fn (PscReport $r) => [
                    'id' => $r->id,
                    'ref_no' => $r->ref_no,
                    'dateof_inspection' => $r->dateof_inspection?->format('Y-m-d'),
                    'placeof_inspection' => $r->placeof_inspection,
                    'name_psco' => $r->name_psco,
                    'mou' => $r->mou?->name ?? $r->mou_others ?? '',
                ])->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/kpi/psc-inspections/reports-by-mou?mou_id=&from=&to=
     */
    public function reportsByMou(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyReportsByMou((string) $request->query('mou_id'), $from, $to, TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => KpiPscInspectionsRepository::mouReportColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->reportsByMou(
            (int) $request->query('mou_id'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiPscInspectionsRepository::mouReportColumns(),
                'rows' => collect($paginator->items())->map(fn (PscReport $r) => [
                    'id' => $r->id,
                    'ref_no' => $r->ref_no,
                    'vessel' => $r->vessel?->display_name ?? '',
                    'dateof_inspection' => $r->dateof_inspection?->format('Y-m-d'),
                    'placeof_inspection' => $r->placeof_inspection,
                    'name_psco' => $r->name_psco,
                ])->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/kpi/psc-inspections/nonconformities-by-vessel?vessel_id=&from=&to=
     */
    public function nonConformitiesByVessel(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        if (LegacyDb::isConfigured()) {
            $result = $this->kpi->legacyNonConformitiesByVessel((string) $request->query('vessel_id'), $from, $to, TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => KpiPscInspectionsRepository::nonconformityColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->kpi->nonConformitiesByVessel(
            (int) $request->query('vessel_id'),
            $from,
            $to,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => KpiPscInspectionsRepository::nonconformityColumns(),
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
