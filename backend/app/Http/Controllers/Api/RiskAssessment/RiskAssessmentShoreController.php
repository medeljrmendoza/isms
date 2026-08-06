<?php

namespace App\Http\Controllers\Api\RiskAssessment;

use App\Http\Controllers\Controller;
use App\Repositories\RiskAssessment\RiskAssessmentShoreRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Risk_assessment_shore.php. Read-only:
 * Add/Edit/Delete/Reopen never had a legacy write-back path built, so
 * they're not offered here — see RiskAssessmentShoreRepository.
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
                'vessels' => $this->riskAssessmentsShore->legacyVesselOptions($request->user()?->legacy_user_id),
                'years' => $this->riskAssessmentsShore->legacyYears(),
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

    /**
     * GET /api/risk-assessments-shore/{riskAssessmentShore}
     */
    public function show(string $riskAssessmentShore): JsonResponse
    {
        $detail = $this->riskAssessmentsShore->legacyDetail($riskAssessmentShore);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
