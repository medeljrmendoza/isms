<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Claims\ClaimRepository;
use App\Repositories\CommitteeMeetings\CommitteeMeetingRepository;
use App\Repositories\CompanyDocumentation\CompanyDocumentationRepository;
use App\Repositories\CompanyInspections\AuditReportRepository;
use App\Repositories\Defects\DefectRepository;
use App\Repositories\Drills\DrillRepository;
use App\Repositories\ExposureHours\ExposureHoursRepository;
use App\Repositories\ExternalAudits\ExternalAuditReportRepository;
use App\Repositories\FlagState\FlagStateReportRepository;
use App\Repositories\IncidentReports\IncidentReportRepository;
use App\Repositories\InternalAudits\InternalAuditReportRepository;
use App\Repositories\IspsReview\IspsReviewRepository;
use App\Repositories\ManualPublish\ManualDocumentPublishRepository;
use App\Repositories\ManualPublish\SmsVersionMonitoringRepository;
use App\Repositories\MasterReview\MasterReviewRepository;
use App\Repositories\Nonconformities\NonconformityRepository;
use App\Repositories\NonSire\NonSireReportRepository;
use App\Repositories\PendingItems\PendingItemsRepository;
use App\Repositories\Pms\PmsRepository;
use App\Repositories\PscReports\PscReportRepository;
use App\Repositories\RiskAssessment\RiskAssessmentRepository;
use App\Repositories\Sire\SireReportRepository;
use App\Repositories\Tasks\TaskRepository;
use App\Repositories\VesselDocumentation\VesselDocumentationRepository;
use App\Repositories\VesselExports\VesselExportRepository;
use App\Services\DashboardService;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly NonconformityRepository $nonconformities,
        private readonly ClaimRepository $claims,
        private readonly ExposureHoursRepository $exposureHours,
        private readonly TaskRepository $tasks,
        private readonly IncidentReportRepository $incidentReports,
        private readonly AuditReportRepository $auditReports,
        private readonly InternalAuditReportRepository $internalAuditReports,
        private readonly PscReportRepository $pscReports,
        private readonly ExternalAuditReportRepository $externalAuditReports,
        private readonly RiskAssessmentRepository $riskAssessments,
        private readonly SireReportRepository $sireReports,
        private readonly NonSireReportRepository $nonSireReports,
        private readonly FlagStateReportRepository $flagStateReports,
        private readonly CommitteeMeetingRepository $committeeMeetings,
        private readonly CompanyDocumentationRepository $companyDocumentation,
        private readonly DefectRepository $defects,
        private readonly ManualDocumentPublishRepository $manualDocumentPublish,
        private readonly MasterReviewRepository $masterReviews,
        private readonly IspsReviewRepository $ispsReviews,
        private readonly DrillRepository $drills,
        private readonly PmsRepository $pms,
        private readonly SmsVersionMonitoringRepository $smsVersionMonitoring,
        private readonly VesselDocumentationRepository $vesselDocumentation,
        private readonly PendingItemsRepository $pendingItems,
        private readonly VesselExportRepository $vesselExports,
    ) {}

    /**
     * GET /api/dashboard
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'dashlets' => $this->dashboardService->getDashletData(),
            ],
        ]);
    }

    /**
     * GET /api/dashboard/pending-items
     */
    public function pendingItemsTable(Request $request): JsonResponse
    {
        $rows = $this->pendingItems->legacyTable($request->user()?->legacy_user_id);

        return response()->json(['data' => ['rows' => $rows]]);
    }

    /**
     * GET /api/notifications/counts
     */
    public function notificationCounts(): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->getNotificationCounts(),
        ]);
    }

    /**
     * GET /api/dashboard/nonconformities
     */
    public function nonconformitiesTable(Request $request): JsonResponse
    {
        $result = $this->nonconformities->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => NonconformityRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/nonconformities/{id} — powers the dashlet's
     * "click NCR No. to view" (see NonconformityRepository::legacyDetail()).
     * {id} is the raw legacy ncID string, matching whatever `record_id`
     * the table endpoint just returned for that row.
     */
    public function nonconformityDetail(string $id): JsonResponse
    {
        $detail = $this->nonconformities->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/claims
     */
    public function claimsTable(Request $request): JsonResponse
    {
        $result = $this->claims->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => ClaimRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/exposure-hours
     */
    public function exposureHoursTable(Request $request): JsonResponse
    {
        $result = $this->exposureHours->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => ExposureHoursRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/assigned-tasks
     */
    public function assignedTasksTable(Request $request): JsonResponse
    {
        $result = $this->tasks->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => TaskRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/vessel-exports
     */
    public function vesselExportsTable(Request $request): JsonResponse
    {
        $result = $this->vesselExports->legacyTable(TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => VesselExportRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/incident-reports
     */
    public function incidentReportsTable(Request $request): JsonResponse
    {
        $result = $this->incidentReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => IncidentReportRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/incident-reports/{id}
     */
    public function incidentReportDetail(string $id): JsonResponse
    {
        $detail = $this->incidentReports->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/company-inspections
     */
    public function companyInspectionsTable(Request $request): JsonResponse
    {
        $result = $this->auditReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => AuditReportRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/company-inspections/{id}
     */
    public function companyInspectionDetail(string $id): JsonResponse
    {
        $detail = $this->auditReports->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/internal-audits
     */
    public function internalAuditsTable(Request $request): JsonResponse
    {
        $result = $this->internalAuditReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => InternalAuditReportRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/internal-audits/{id}
     */
    public function internalAuditDetail(string $id): JsonResponse
    {
        $detail = $this->internalAuditReports->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/psc-inspections
     */
    public function pscInspectionsTable(Request $request): JsonResponse
    {
        $result = $this->pscReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => PscReportRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/psc-inspections/{id}
     */
    public function pscInspectionDetail(string $id): JsonResponse
    {
        $detail = $this->pscReports->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/external-audits
     */
    public function externalAuditsTable(Request $request): JsonResponse
    {
        $result = $this->externalAuditReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => ExternalAuditReportRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/external-audits/{id}
     */
    public function externalAuditDetail(string $id): JsonResponse
    {
        $detail = $this->externalAuditReports->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/risk-assessments
     */
    public function riskAssessmentsTable(Request $request): JsonResponse
    {
        $result = $this->riskAssessments->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => RiskAssessmentRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/risk-assessments/{id}
     */
    public function riskAssessmentDetail(string $id): JsonResponse
    {
        $detail = $this->riskAssessments->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/sire
     */
    public function sireTable(Request $request): JsonResponse
    {
        $result = $this->sireReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => SireReportRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/sire/{id}
     */
    public function sireDetail(string $id): JsonResponse
    {
        $detail = $this->sireReports->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/non-sire
     */
    public function nonSireTable(Request $request): JsonResponse
    {
        $result = $this->nonSireReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => NonSireReportRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/non-sire/{id}
     */
    public function nonSireDetail(string $id): JsonResponse
    {
        $detail = $this->nonSireReports->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/flag-state
     */
    public function flagStateTable(Request $request): JsonResponse
    {
        $result = $this->flagStateReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => FlagStateReportRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/flag-state/{id}
     */
    public function flagStateDetail(string $id): JsonResponse
    {
        $detail = $this->flagStateReports->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/committee-meetings
     */
    public function committeeMeetingsTable(Request $request): JsonResponse
    {
        $result = $this->committeeMeetings->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => CommitteeMeetingRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/committee-meetings/{id}
     */
    public function committeeMeetingDetail(string $id): JsonResponse
    {
        $detail = $this->committeeMeetings->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/company-documentation
     */
    public function companyDocumentationTable(Request $request): JsonResponse
    {
        $result = $this->companyDocumentation->legacyTable(TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => CompanyDocumentationRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/defects
     */
    public function defectsTable(Request $request): JsonResponse
    {
        $result = $this->defects->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => DefectRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/defects/{id} — powers the dashlet's "click SL
     * No. to view" (see DefectRepository::legacyDetail()). {id} is the
     * raw legacy defectID string, matching whatever `record_id` the
     * table endpoint just returned for that row.
     */
    public function defectDetail(string $id): JsonResponse
    {
        $detail = $this->defects->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/sms-publish-manual
     */
    public function smsPublishManualTable(Request $request): JsonResponse
    {
        $result = $this->manualDocumentPublish->legacyTable(TableQuery::fromRequest($request));

        return response()->json(['data' => ['columns' => ManualDocumentPublishRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/master-reviews
     */
    public function masterReviewsTable(Request $request): JsonResponse
    {
        $result = $this->masterReviews->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => MasterReviewRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/master-reviews/{id}
     */
    public function masterReviewDetail(string $id): JsonResponse
    {
        $detail = $this->masterReviews->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/isps-reviews
     */
    public function ispsReviewsTable(Request $request): JsonResponse
    {
        $result = $this->ispsReviews->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => IspsReviewRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/isps-reviews/{id}
     */
    public function ispsReviewDetail(string $id): JsonResponse
    {
        $detail = $this->ispsReviews->legacyDetail($id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/drills
     */
    public function drillsTable(Request $request): JsonResponse
    {
        $result = $this->drills->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => DrillRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/pms
     */
    public function pmsTable(Request $request): JsonResponse
    {
        $result = $this->pms->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => PmsRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/sms-version-monitoring
     */
    public function smsVersionMonitoringTable(Request $request): JsonResponse
    {
        $result = $this->smsVersionMonitoring->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => SmsVersionMonitoringRepository::columns(), ...$result]]);
    }

    /**
     * GET /api/dashboard/vessel-documentation
     */
    public function vesselDocumentationTable(Request $request): JsonResponse
    {
        $result = $this->vesselDocumentation->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

        return response()->json(['data' => ['columns' => VesselDocumentationRepository::columns(), ...$result]]);
    }
}
