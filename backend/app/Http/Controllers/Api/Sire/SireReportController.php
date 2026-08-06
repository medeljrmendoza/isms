<?php

namespace App\Http\Controllers\Api\Sire;

use App\Http\Controllers\Controller;
use App\Repositories\Sire\SireReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Sire.php. Read-only: Add/Edit/Publish/
 * Approve/Delete never had a legacy write-back path built, so they're
 * not offered here — see SireReportRepository.
 */
class SireReportController extends Controller
{
    public function __construct(private readonly SireReportRepository $sireReports) {}

    /**
     * GET /api/sire-reports
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');
        $vesselId = $vesselId !== '' ? $vesselId : null;

        $result = $this->sireReports->legacyFullTable(
            TableQuery::fromRequest($request),
            $vesselId,
            $request->user()?->legacy_user_id,
        );

        return response()->json([
            'data' => [
                'columns' => SireReportRepository::moduleColumns(),
                'rows' => $result['rows'],
                'meta' => $result['meta'],
            ],
        ]);
    }

    /**
     * GET /api/sire-reports/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->sireReports->legacyVesselOptions($request->user()?->legacy_user_id),
            ],
        ]);
    }

    /**
     * GET /api/sire-reports/{sireReport}
     */
    public function show(string $sireReport): JsonResponse
    {
        $detail = $this->sireReports->legacyDetail($sireReport);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
