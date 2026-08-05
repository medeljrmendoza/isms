<?php

namespace App\Http\Controllers\Api\RiskAssessment;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskAssessment\RiskAssessmentApprovalRequest;
use App\Models\RiskAssessment\RiskAssessment;
use App\Repositories\RiskAssessment\RiskAssessmentRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Risk_assessment_vessel.php. Not ported:
 * add_report() (every substantive report field is unconditionally
 * re-read from the existing row — the only real user input it accepts
 * is the two approval tracks, ported here as approveShore/
 * approveMarine), reopen_report() (a live endpoint, but its list-view
 * trigger is commented out and no other view renders one — unreachable,
 * same as Drill Reports' phantom delete button), and the Category →
 * Operation → standard-Hazard-template cascade endpoints
 * (get_operation/view_riskHazzard/operation_hazzard) — they only power
 * the add flow's hazard auto-suggest, which is itself unreachable since
 * every field in that form ships `disabled` in the legacy markup.
 * There is no create or delete path anywhere in this admin: reports
 * originate from the unmigrated vessel-side app.
 */
class RiskAssessmentController extends Controller
{
    public function __construct(private readonly RiskAssessmentRepository $riskAssessments) {}

    /**
     * GET /api/risk-assessments/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => LegacyDb::isConfigured()
                    ? $this->riskAssessments->legacyVesselOptions($request->user()?->legacy_user_id)
                    : $this->riskAssessments->vesselOptions(),
                'years' => LegacyDb::isConfigured()
                    ? $this->riskAssessments->legacyYears($request->user()?->legacy_user_id)
                    : $this->riskAssessments->years(),
            ],
        ]);
    }

    /**
     * GET /api/risk-assessments?vessel_id=&year=
     */
    public function index(Request $request): JsonResponse
    {
        $year = $request->query('year') !== null ? (int) $request->query('year') : null;

        if (LegacyDb::isConfigured()) {
            $vesselId = $request->query('vessel_id');
            $vesselId = $vesselId !== '' ? $vesselId : null;

            $result = $this->riskAssessments->legacyFullTable(
                $vesselId,
                $year,
                TableQuery::fromRequest($request),
                $request->user()?->legacy_user_id,
            );

            return response()->json([
                'data' => [
                    'columns' => RiskAssessmentRepository::fullColumns(),
                    'rows' => $result['rows'],
                    'meta' => $result['meta'],
                ],
            ]);
        }

        $vesselId = $request->query('vessel_id') !== null ? (int) $request->query('vessel_id') : null;
        $paginator = $this->riskAssessments->fullTable($vesselId, $year, TableQuery::fromRequest($request));

        return response()->json([
            'data' => [
                'columns' => RiskAssessmentRepository::fullColumns(),
                'rows' => collect($paginator->items())->map(fn (RiskAssessment $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/risk-assessments/{riskAssessment}
     */
    public function show(string $riskAssessment): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $detail = $this->riskAssessments->legacyDetail($riskAssessment);
            abort_if($detail === null, 404);

            return response()->json(['data' => $detail]);
        }

        $model = RiskAssessment::query()->with(['vessel', 'riskCategory', 'riskOperation', 'hazards', 'people'])->findOrFail((int) $riskAssessment);

        return response()->json(['data' => $this->mapDetail($model)]);
    }

    /**
     * POST /api/risk-assessments/{riskAssessment}/approve-shore
     */
    public function approveShore(RiskAssessmentApprovalRequest $request, RiskAssessment $riskAssessment): JsonResponse
    {
        abort_unless($riskAssessment->approval_from_shore, 422, 'This report does not require Technical Superintendent approval.');

        $data = $request->validated();
        $riskAssessment = $this->riskAssessments->approveShore($riskAssessment, $data['approved'], $data['date_approved'] ?? null, $data['remarks'] ?? null);
        $riskAssessment->load(['vessel', 'riskCategory', 'riskOperation', 'hazards', 'people']);

        return response()->json(['data' => $this->mapDetail($riskAssessment)]);
    }

    /**
     * POST /api/risk-assessments/{riskAssessment}/approve-marine
     */
    public function approveMarine(RiskAssessmentApprovalRequest $request, RiskAssessment $riskAssessment): JsonResponse
    {
        abort_unless($riskAssessment->approval_from_marine, 422, 'This report does not require Marine Superintendent approval.');

        $data = $request->validated();
        $riskAssessment = $this->riskAssessments->approveMarine($riskAssessment, $data['approved'], $data['date_approved'] ?? null, $data['remarks'] ?? null);
        $riskAssessment->load(['vessel', 'riskCategory', 'riskOperation', 'hazards', 'people']);

        return response()->json(['data' => $this->mapDetail($riskAssessment)]);
    }

    private function mapRow(RiskAssessment $r): array
    {
        return [
            'id' => $r->id,
            'report_no' => $r->report_no,
            'vessel' => $r->vessel?->display_name ?? '',
            'risk_date' => $r->risk_date?->format('Y-m-d'),
            'port' => $r->port,
            'category' => $r->category_label,
            'task' => $r->operation_label,
            'approval_from_shore' => $r->approval_from_shore,
            'shore_is_approved' => $r->shore_is_approved,
            'approval_from_marine' => $r->approval_from_marine,
            'marine_is_approved' => $r->marine_is_approved,
            'hazard_count' => $r->hazards_count,
            'can_edit' => $r->hazards_count > 0 && ($r->approval_from_shore || $r->approval_from_marine),
        ];
    }

    private function mapDetail(RiskAssessment $r): array
    {
        return [
            ...$this->mapRow($r),
            'vessel_id' => $r->vessel_id,
            'risk_schedule' => $r->risk_schedule?->format('Y-m-d'),
            'department' => $r->department,
            'activity' => $r->activity,
            'risk_category_id' => $r->risk_category_id,
            'other_category_name' => $r->other_category_name,
            'risk_operation_id' => $r->risk_operation_id,
            'other_operation_name' => $r->other_operation_name,
            'overall_risk' => $r->overall_risk,
            'master' => $r->master,
            'ce_co' => $r->ce_co,
            'vessel_remarks' => $r->vessel_remarks,
            'date_approved' => $r->date_approved?->format('Y-m-d'),
            'shore_remarks' => $r->shore_remarks,
            'marine_date_approved' => $r->marine_date_approved?->format('Y-m-d'),
            'marine_remarks' => $r->marine_remarks,
            'date_closed' => $r->date_closed?->format('Y-m-d'),
            'hazards' => $r->hazards->map(fn ($h) => [
                'id' => $h->id,
                'arrangement' => $h->arrangement,
                'unwanted_consequences' => $h->unwanted_consequences,
                'underlying_causes' => $h->underlying_causes,
                'severity' => $h->severity,
                'likelihood' => $h->likelihood,
                'risk' => $h->risk,
                'existing_control' => $h->existing_control,
                'additional_control' => $h->additional_control,
                're_severity' => $h->re_severity,
                're_likelihood' => $h->re_likelihood,
                're_risk' => $h->re_risk,
            ])->all(),
            'people' => $r->people->map(fn ($p) => [
                'id' => $p->id,
                'arrangement' => $p->arrangement,
                'person_details' => $p->person_details,
            ])->all(),
        ];
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
