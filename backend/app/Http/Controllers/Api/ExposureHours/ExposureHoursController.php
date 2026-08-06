<?php

namespace App\Http\Controllers\Api\ExposureHours;

use App\Http\Controllers\Controller;
use App\Repositories\ExposureHours\ExposureHoursRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Exposure_hours.php. Not ported: the tb_logs
 * audit trail, user_level-gated button visibility, and
 * summary_export_to_excel() (an .xlsx download of the summary report —
 * no Excel export exists anywhere else in this migration either). The
 * static Legends page (legends()) has no data at all in legacy — it's a
 * fixed FAT/PTD/PPD/LWC/RWC/MTC/LTI/TRC/LTIF/TRCF glossary, so it's
 * rendered client-side from a constant instead of a backend endpoint.
 *
 * Read-only: Add/Edit/Delete never had a legacy write-back path built,
 * so they're not offered here — see ExposureHoursRepository.
 */
class ExposureHoursController extends Controller
{
    public function __construct(private readonly ExposureHoursRepository $exposureHours) {}

    /**
     * GET /api/exposure-hours/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->exposureHours->legacyVesselOptions($request->user()?->legacy_user_id),
            ],
        ]);
    }

    /**
     * GET /api/exposure-hours/summary?vessel_id=&date_from=&date_to=
     */
    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->exposureHours->legacySummary(
                $request->query('vessel_id'),
                $request->query('date_from') ?: null,
                $request->query('date_to') ?: null,
                $request->user()?->legacy_user_id,
            ),
        ]);
    }

    /**
     * GET /api/exposure-hours-records?vessel_id=&date_from=&date_to=
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');

        if ($vesselId === null || $vesselId === '') {
            return response()->json(['data' => ['columns' => ExposureHoursRepository::recordColumns(), 'rows' => [], 'meta' => null]]);
        }

        $result = $this->exposureHours->legacyFullTable(
            $vesselId,
            $request->query('date_from') ?: null,
            $request->query('date_to') ?: null,
            TableQuery::fromRequest($request),
            $request->user()?->legacy_user_id,
        );

        return response()->json([
            'data' => [
                'columns' => ExposureHoursRepository::recordColumns(),
                'rows' => $result['rows'],
                'meta' => $result['meta'],
            ],
        ]);
    }

    /**
     * GET /api/exposure-hours-records/{exposureHoursRecord}
     */
    public function show(string $exposureHoursRecord): JsonResponse
    {
        $detail = $this->exposureHours->legacyDetail($exposureHoursRecord);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
