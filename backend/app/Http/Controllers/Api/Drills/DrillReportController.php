<?php

namespace App\Http\Controllers\Api\Drills;

use App\Http\Controllers\Controller;
use App\Http\Requests\Drills\DrillReportRequest;
use App\Models\Drills\DrillReport;
use App\Repositories\Drills\DrillRepository;
use App\Support\LegacyDb;
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
 * There's deliberately no create or delete endpoint here — see
 * DrillRepository's docblock for why.
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
                'vessels' => LegacyDb::isConfigured()
                    ? $this->drills->legacyVesselOptions($request->user()?->legacy_user_id)
                    : $this->drills->vesselOptions(),
                'years' => LegacyDb::isConfigured()
                    ? $this->drills->legacyYearOptions()
                    : $this->drills->yearOptions(),
            ],
        ]);
    }

    /**
     * GET /api/drill-lists/calendar?vessel_id=&year=
     */
    public function calendar(Request $request): JsonResponse
    {
        $year = (int) ($request->query('year') ?: now()->year);

        if (LegacyDb::isConfigured()) {
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

        $vesselId = (int) $request->query('vessel_id');

        if ($vesselId === 0) {
            return response()->json(['data' => ['rows' => [], 'year' => $year]]);
        }

        return response()->json([
            'data' => [
                'rows' => $this->drills->calendarGrid($vesselId, $year)->values(),
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

        if (LegacyDb::isConfigured()) {
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

        $drillListId = (int) $request->query('drill_list_id');
        $vesselId = (int) $request->query('vessel_id');

        $reports = $this->drills->reportsForCell($drillListId, $vesselId, $year, $month);

        return response()->json([
            'data' => $reports->map(fn (DrillReport $r) => [
                'id' => $r->id,
                'drill_date' => $r->drill_date->format('Y-m-d'),
                'drill_position' => $r->drill_position,
                'drill_time_from' => $r->drill_time_from,
                'can_edit' => true,
            ])->all(),
        ]);
    }

    /**
     * GET /api/drill-reports/{drillReport}
     */
    public function show(string $drillReport): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $detail = $this->drills->legacyDetail($drillReport);
            abort_if($detail === null, 404);

            return response()->json(['data' => $detail]);
        }

        $model = DrillReport::query()->with(['vessel', 'drillList', 'crew'])->findOrFail((int) $drillReport);

        return response()->json(['data' => $this->mapDetail($model)]);
    }

    /**
     * PUT /api/drill-reports/{drillReport}
     */
    public function update(DrillReportRequest $request, DrillReport $drillReport): JsonResponse
    {
        $validated = $request->validated();
        $crew = $validated['crew'];
        unset($validated['crew']);

        $drillReport = $this->drills->update($drillReport, $validated, $crew);
        $drillReport->load(['vessel', 'drillList', 'crew']);

        return response()->json(['data' => $this->mapDetail($drillReport)]);
    }

    private function mapDetail(DrillReport $r): array
    {
        return [
            'id' => $r->id,
            'vessel' => $r->vessel?->display_name ?? '',
            'drill_list_id' => $r->drill_list_id,
            'drill_name' => $r->drillList?->name ?? '',
            'drill_type' => $r->drillList?->drill_type,
            'master_name' => $r->master_name,
            'drill_date' => $r->drill_date->format('Y-m-d'),
            'drill_time_from' => $r->drill_time_from,
            'drill_position' => $r->drill_position,
            'drill_details' => $r->drill_details,
            'drill_deficiencies' => $r->drill_deficiencies,
            'drill_corrective_action' => $r->drill_corrective_action,
            'report_date' => $r->report_date?->format('Y-m-d'),
            'vessel_remarks' => $r->vessel_remarks,
            'receipt_date' => $r->receipt_date?->format('Y-m-d'),
            'shore_remarks' => $r->shore_remarks,
            'can_edit' => true,
            'crew' => $r->crew->map(fn ($c) => ['crew_name' => $c->crew_name])->all(),
        ];
    }
}
