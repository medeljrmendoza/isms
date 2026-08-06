<?php

namespace App\Http\Controllers\Api\NonSire;

use App\Http\Controllers\Controller;
use App\Repositories\NonSire\NonSireReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Non_sire.php. Read-only: Add/Edit/Publish/
 * Approve/Delete never had a legacy write-back path built, so they're
 * not offered here — see NonSireReportRepository.
 */
class NonSireReportController extends Controller
{
    public function __construct(private readonly NonSireReportRepository $nonSireReports) {}

    /**
     * GET /api/non-sire-reports
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');
        $vesselId = $vesselId !== '' ? $vesselId : null;

        $result = $this->nonSireReports->legacyFullTable(
            TableQuery::fromRequest($request),
            $vesselId,
            $request->user()?->legacy_user_id,
        );

        return response()->json([
            'data' => [
                'columns' => NonSireReportRepository::moduleColumns(),
                'rows' => $result['rows'],
                'meta' => $result['meta'],
            ],
        ]);
    }

    /**
     * GET /api/non-sire-reports/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->nonSireReports->legacyVesselOptions($request->user()?->legacy_user_id),
            ],
        ]);
    }

    /**
     * GET /api/non-sire-reports/{nonSireReport}
     */
    public function show(string $nonSireReport): JsonResponse
    {
        $detail = $this->nonSireReports->legacyDetail($nonSireReport);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
