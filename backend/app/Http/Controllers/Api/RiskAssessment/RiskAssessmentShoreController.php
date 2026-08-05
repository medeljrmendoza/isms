<?php

namespace App\Http\Controllers\Api\RiskAssessment;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskAssessment\RiskAssessmentShoreRequest;
use App\Models\RiskAssessment\RiskAssessmentShore;
use App\Repositories\RiskAssessment\RiskAssessmentShoreRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Risk_assessment_shore.php. Not ported: the
 * "Approve" DataTable button wired to approve_riskassessment_report_shore()
 * — that JS function is never defined in any of the four Shore view
 * files, a dead reference, so there's no live approve action here (the
 * approval sections are just fields on the regular update payload, same
 * as everything else on the form). Also not ported: user_level-gated
 * button visibility (SUPERADMIN/BTSOLVE vs ADMIN vs MEMBER) — dropped
 * project-wide, see every other CRUD module's controller docblock.
 */
class RiskAssessmentShoreController extends Controller
{
    public function __construct(private readonly RiskAssessmentShoreRepository $riskAssessmentsShore) {}

    /**
     * GET /api/risk-assessments-shore/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => LegacyDb::isConfigured()
                    ? $this->riskAssessmentsShore->legacyVesselOptions($request->user()?->legacy_user_id)
                    : $this->riskAssessmentsShore->vesselOptions(),
                'categories' => $this->riskAssessmentsShore->categoryOptions(),
                'operations' => $this->riskAssessmentsShore->operationOptions(),
                'years' => LegacyDb::isConfigured()
                    ? $this->riskAssessmentsShore->legacyYears()
                    : $this->riskAssessmentsShore->years(),
            ],
        ]);
    }

    /**
     * GET /api/risk-assessments-shore?vessel_id=&year=
     */
    public function index(Request $request): JsonResponse
    {
        $year = $request->query('year') !== null && $request->query('year') !== ''
            ? (int) $request->query('year') : null;

        if (LegacyDb::isConfigured()) {
            $vesselId = $request->query('vessel_id');
            $vesselId = $vesselId !== '' ? $vesselId : null;

            $result = $this->riskAssessmentsShore->legacyTable(
                TableQuery::fromRequest($request),
                $vesselId,
                $year,
                $request->user()?->legacy_user_id,
            );

            return response()->json([
                'data' => [
                    'columns' => RiskAssessmentShoreRepository::columns(),
                    'rows' => $result['rows'],
                    'meta' => $result['meta'],
                ],
            ]);
        }

        $vesselId = $request->query('vessel_id') !== null && $request->query('vessel_id') !== ''
            ? (int) $request->query('vessel_id') : null;

        $paginator = $this->riskAssessmentsShore->table(TableQuery::fromRequest($request), $vesselId, $year);

        return response()->json([
            'data' => [
                'columns' => RiskAssessmentShoreRepository::columns(),
                'rows' => collect($paginator->items())->map(fn (RiskAssessmentShore $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/risk-assessments-shore/{riskAssessmentShore}
     */
    public function show(string $riskAssessmentShore): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $detail = $this->riskAssessmentsShore->legacyDetail($riskAssessmentShore);
            abort_if($detail === null, 404);

            return response()->json(['data' => $detail]);
        }

        $model = RiskAssessmentShore::query()->with(['vessel', 'riskCategoryShore', 'riskOperationShore', 'hazards', 'people'])->findOrFail((int) $riskAssessmentShore);

        return response()->json(['data' => $this->mapDetail($model)]);
    }

    /**
     * POST /api/risk-assessments-shore
     */
    public function store(RiskAssessmentShoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $hazards = $data['hazards'] ?? [];
        $people = $data['people'] ?? [];
        unset($data['hazards'], $data['people']);

        if ($data['report_type'] === 'SHORE') {
            $data['vessel_id'] = null;
        }

        $report = $this->riskAssessmentsShore->create($data, $hazards, $people);
        $report->load(['vessel', 'riskCategoryShore', 'riskOperationShore', 'hazards', 'people']);

        return response()->json(['data' => $this->mapDetail($report)], 201);
    }

    /**
     * PUT /api/risk-assessments-shore/{riskAssessmentShore}
     */
    public function update(RiskAssessmentShoreRequest $request, RiskAssessmentShore $riskAssessmentShore): JsonResponse
    {
        $data = $request->validated();
        $hazards = $data['hazards'] ?? [];
        $people = $data['people'] ?? [];
        unset($data['hazards'], $data['people']);

        $riskAssessmentShore = $this->riskAssessmentsShore->update($riskAssessmentShore, $data, $hazards, $people);
        $riskAssessmentShore->load(['vessel', 'riskCategoryShore', 'riskOperationShore', 'hazards', 'people']);

        return response()->json(['data' => $this->mapDetail($riskAssessmentShore)]);
    }

    /**
     * POST /api/risk-assessments-shore/{riskAssessmentShore}/reopen
     */
    public function reopen(RiskAssessmentShore $riskAssessmentShore): JsonResponse
    {
        abort_if($riskAssessmentShore->date_closed === null, 422, 'This report is not closed.');

        $riskAssessmentShore = $this->riskAssessmentsShore->reopen($riskAssessmentShore);
        $riskAssessmentShore->load(['vessel', 'riskCategoryShore', 'riskOperationShore', 'hazards', 'people']);

        return response()->json(['data' => $this->mapDetail($riskAssessmentShore)]);
    }

    /**
     * DELETE /api/risk-assessments-shore/{riskAssessmentShore}
     */
    public function destroy(RiskAssessmentShore $riskAssessmentShore): JsonResponse
    {
        abort_unless($riskAssessmentShore->date_closed === null, 422, 'A closed report must be re-opened before it can be deleted.');

        $this->riskAssessmentsShore->delete($riskAssessmentShore);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function mapRow(RiskAssessmentShore $r): array
    {
        return [
            'id' => $r->id,
            'report_no' => $r->report_no,
            'report_type' => $r->report_type,
            'vessel' => $r->vessel?->display_name ?? '',
            'risk_date' => $r->risk_date?->format('Y-m-d'),
            'port' => $r->port,
            'category' => $r->category_label,
            'task' => $r->operation_label,
            'approval_from_shore' => $r->approval_from_shore,
            'shore_is_approved' => $r->shore_is_approved,
            'approval_from_marine' => $r->approval_from_marine,
            'marine_is_approved' => $r->marine_is_approved,
            'date_closed' => $r->date_closed?->format('Y-m-d'),
            'hazard_count' => $r->hazards_count,
            'can_edit' => true,
            'can_delete' => $r->date_closed === null,
            'can_reopen' => $r->date_closed !== null,
        ];
    }

    private function mapDetail(RiskAssessmentShore $r): array
    {
        return [
            ...$this->mapRow($r),
            'vessel_id' => $r->vessel_id,
            'risk_schedule' => $r->risk_schedule?->format('Y-m-d'),
            'department' => $r->department,
            'activity' => $r->activity,
            'risk_category_shore_id' => $r->risk_category_shore_id,
            'other_category_name' => $r->other_category_name,
            'risk_operation_shore_id' => $r->risk_operation_shore_id,
            'other_operation_name' => $r->other_operation_name,
            'overall_risk' => $r->overall_risk,
            'remarks' => $r->remarks,
            'date_approved' => $r->date_approved?->format('Y-m-d'),
            'shore_remarks' => $r->shore_remarks,
            'marine_date_approved' => $r->marine_date_approved?->format('Y-m-d'),
            'marine_remarks' => $r->marine_remarks,
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
