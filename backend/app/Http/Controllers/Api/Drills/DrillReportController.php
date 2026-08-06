<?php

namespace App\Http\Controllers\Api\Drills;

use App\Http\Controllers\Controller;
use App\Repositories\Drills\DrillRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Drill.php. Not ported: the tb_logs audit
 * trail, user_level-gated button visibility, and the printable
 * header/footer (tied to tb_report_footer, a Setup-managed
 * report-branding feature also dropped in Committee Meeting). The
 * "auto-populate frequency/instructions from the selected drill" AJAX
 * convenience (get_drill_details()) is dropped too — it only ever
 * backed a create flow that's commented out in legacy's own view, so
 * there's nothing live to port.
 *
 * Read-only: legacy never lets shore create, edit, or delete a drill
 * report (can_edit is always false; add_record() only ever edits an
 * existing row from the unmigrated vessel-side app, and delete_record()
 * doesn't exist in the controller at all) — see DrillRepository.
 */
class DrillReportController extends Controller
{
    public function __construct(private readonly DrillRepository $drills) {}

    /**
     * GET /api/drill-lists/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->drills->legacyVesselOptions($request->user()?->legacy_user_id),
                'years' => $this->drills->legacyYearOptions(),
            ],
        ]);
    }

    /**
     * GET /api/drill-lists/calendar?vessel_id=&year=
     */
    public function calendar(Request $request): JsonResponse
    {
        $year = (int) ($request->query('year') ?: now()->year);
        $vesselId = $request->query('vessel_id');

        if ($vesselId === null || $vesselId === '') {
            return response()->json(['data' => ['rows' => [], 'year' => $year]]);
        }

        return response()->json([
            'data' => [
                'rows' => $this->drills->legacyCalendarGrid($vesselId, $year, $request->user()?->legacy_user_id)->values(),
                'year' => $year,
            ],
        ]);
    }

    /**
     * GET /api/drill-reports?drill_list_id=&vessel_id=&year=&month=
     */
    public function cell(Request $request): JsonResponse
    {
        $year = (int) $request->query('year');
        $month = (int) $request->query('month');
        $drillListId = $request->query('drill_list_id');
        $vesselId = $request->query('vessel_id');

        $reports = $this->drills->legacyReportsForCell($drillListId, $vesselId, $year, $month);

        return response()->json([
            'data' => $reports->map(fn ($r) => [
                'id' => $r->drillID,
                'drill_date' => $r->drill_date,
                'drill_position' => $r->drill_position,
                'drill_time_from' => $r->drill_time_from,
                'can_edit' => false,
            ])->all(),
        ]);
    }

    /**
     * GET /api/drill-reports/{drillReport}
     */
    public function show(string $drillReport): JsonResponse
    {
        $detail = $this->drills->legacyDetail($drillReport);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
