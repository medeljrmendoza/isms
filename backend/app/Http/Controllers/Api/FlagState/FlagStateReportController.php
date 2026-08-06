<?php

namespace App\Http\Controllers\Api\FlagState;

use App\Http\Controllers\Controller;
use App\Repositories\FlagState\FlagStateReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Flag_state.php. Read-only: Add/Edit/Publish/
 * Approve/Delete never had a legacy write-back path built, so they're
 * not offered here — see FlagStateReportRepository.
 */
class FlagStateReportController extends Controller
{
    public function __construct(private readonly FlagStateReportRepository $flagStateReports) {}

    /**
     * GET /api/flag-state-reports
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');
        $vesselId = $vesselId !== '' ? $vesselId : null;

        $result = $this->flagStateReports->legacyFullTable(
            TableQuery::fromRequest($request),
            $vesselId,
            $request->user()?->legacy_user_id,
        );

        return response()->json([
            'data' => [
                'columns' => FlagStateReportRepository::moduleColumns(),
                'rows' => $result['rows'],
                'meta' => $result['meta'],
            ],
        ]);
    }

    /**
     * GET /api/flag-state-reports/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->flagStateReports->legacyVesselOptions($request->user()?->legacy_user_id),
            ],
        ]);
    }

    /**
     * GET /api/flag-state-reports/{flagStateReport}
     */
    public function show(string $flagStateReport): JsonResponse
    {
        $detail = $this->flagStateReports->legacyDetail($flagStateReport);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
