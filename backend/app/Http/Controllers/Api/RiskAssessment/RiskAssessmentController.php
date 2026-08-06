<?php

namespace App\Http\Controllers\Api\RiskAssessment;

use App\Http\Controllers\Controller;
use App\Repositories\RiskAssessment\RiskAssessmentRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Risk_assessment_vessel.php. Read-only:
 * approveShore/approveMarine never had a legacy write-back path built,
 * so they're not offered here — see RiskAssessmentRepository. Not
 * ported: add_report() (every substantive report field is
 * unconditionally re-read from the existing row), reopen_report()
 * (unreachable, its list-view trigger is commented out), and the
 * Category → Operation → standard-Hazard-template cascade endpoints
 * (only power the add flow's hazard auto-suggest, itself unreachable
 * since every field in that form ships `disabled` in the legacy
 * markup). There is no create or delete path anywhere in this admin:
 * reports originate from the unmigrated vessel-side app.
 */
class RiskAssessmentController extends Controller
{
    public function __construct(private readonly RiskAssessmentRepository $riskAssessments) {}

    /**
     * GET /api/risk-assessments/options
     */
    public function options(Request $request): JsonResponse
    {
        $legacyUserId = $request->user()?->legacy_user_id;

        return response()->json([
            'data' => [
                'vessels' => $this->riskAssessments->legacyVesselOptions($legacyUserId),
                'years' => $this->riskAssessments->legacyYears($legacyUserId),
            ],
        ]);
    }

    /**
     * GET /api/risk-assessments?vessel_id=&year=
     */
    public function index(Request $request): JsonResponse
    {
        $year = $request->query('year') !== null ? (int) $request->query('year') : null;
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

    /**
     * GET /api/risk-assessments/{riskAssessment}
     */
    public function show(string $riskAssessment): JsonResponse
    {
        $detail = $this->riskAssessments->legacyDetail($riskAssessment);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
