<?php

namespace App\Http\Controllers\Api\PscReports;

use App\Http\Controllers\Controller;
use App\Http\Requests\PscReports\PscReportRequest;
use App\Repositories\PscReports\PscReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Psc.php. Add/Edit/Delete write back to the live
 * legacy tb_psc_report table — see PscReportRepository. No Reopen: legacy's
 * own Reopen button is dead code (never wired to the list), so it's not
 * offered here either.
 */
class PscReportController extends Controller
{
    public function __construct(private readonly PscReportRepository $pscReports) {}

    /**
     * GET /api/psc-reports
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');
        $vesselId = $vesselId !== '' ? $vesselId : null;

        $result = $this->pscReports->legacyFullTable(
            TableQuery::fromRequest($request),
            $vesselId,
            $request->user()?->legacy_user_id,
        );

        return response()->json([
            'data' => [
                'columns' => PscReportRepository::moduleColumns(),
                'rows' => $result['rows'],
                'meta' => $result['meta'],
            ],
        ]);
    }

    /**
     * GET /api/psc-reports/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->pscReports->legacyVesselOptions($request->user()?->legacy_user_id),
                'mou_authorities' => $this->pscReports->legacyMouOptions(),
            ],
        ]);
    }

    /**
     * GET /api/psc-reports/{pscReport}
     */
    public function show(string $pscReport): JsonResponse
    {
        $detail = $this->pscReports->legacyDetail($pscReport);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * POST /api/psc-reports
     */
    public function store(PscReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->pscReports->legacySave(null, $request->validated())], 201);
    }

    /**
     * PUT /api/psc-reports/{pscReport}
     */
    public function update(PscReportRequest $request, string $pscReport): JsonResponse
    {
        return response()->json(['data' => $this->pscReports->legacySave($pscReport, $request->validated())]);
    }

    /**
     * DELETE /api/psc-reports/{pscReport}
     */
    public function destroy(string $pscReport): JsonResponse
    {
        $this->pscReports->legacyDelete($pscReport);

        return response()->json(['data' => ['ok' => true]]);
    }
}
