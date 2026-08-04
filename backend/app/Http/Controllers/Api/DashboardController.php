<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claims\Claim;
use App\Models\CommitteeMeetings\CommitteeMeeting;
use App\Models\CompanyDocumentation\CompanyDocumentationRecord;
use App\Models\CompanyInspections\AuditReport;
use App\Models\Defects\Defect;
use App\Models\ExternalAudits\ExternalAuditReport;
use App\Models\FlagState\FlagStateReport;
use App\Models\IncidentReports\IncidentReport;
use App\Models\InternalAudits\InternalAuditReport;
use App\Models\IspsReview\IspsReview;
use App\Models\ManualPublish\ManualDocument;
use App\Models\MasterReview\MasterReview;
use App\Models\Nonconformities\Nonconformity;
use App\Models\NonSire\NonSireReport;
use App\Models\PscReports\PscReport;
use App\Models\RiskAssessment\RiskAssessment;
use App\Models\Sire\SireReport;
use App\Models\Tasks\Task;
use App\Models\Vessel;
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
use App\Repositories\Pms\PmsRepository;
use App\Repositories\PscReports\PscReportRepository;
use App\Repositories\RiskAssessment\RiskAssessmentRepository;
use App\Repositories\Sire\SireReportRepository;
use App\Repositories\Tasks\TaskRepository;
use App\Repositories\VesselDocumentation\VesselDocumentationRepository;
use App\Services\DashboardService;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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
        if (LegacyDb::isConfigured()) {
            $result = $this->nonconformities->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => NonconformityRepository::columns(), ...$result]]);
        }

        $paginator = $this->nonconformities->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, NonconformityRepository::columns(), function (Nonconformity $nc) {
            return [
                'record_id' => (string) $nc->id,
                'ncr_no' => $nc->ncr_no,
                'date_of_nc' => $nc->date_of_nc->format('Y-m-d'),
                'vessel_company' => $nc->vessel_company === 'VESSEL'
                    ? ($nc->vessel?->display_name ?? '')
                    : ($nc->company ?? ''),
                'description' => $nc->description,
            ];
        });
    }

    /**
     * GET /api/dashboard/nonconformities/{id} — powers the dashlet's
     * "click NCR No. to view" (see NonconformityRepository::detail()/
     * legacyDetail()). {id} is the local numeric id or, in legacy mode,
     * the raw legacy ncID string — matching whatever `record_id` the
     * table endpoint just returned for that row.
     */
    public function nonconformityDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->nonconformities->legacyDetail($id)
            : $this->nonconformities->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/claims
     */
    public function claimsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->claims->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => ClaimRepository::columns(), ...$result]]);
        }

        $paginator = $this->claims->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, ClaimRepository::columns(), function (Claim $claim) {
            return [
                'claim_no' => $claim->claim_no,
                'claims_category' => $claim->claims_category,
                'vessel' => $claim->vessel?->display_name ?? '',
                'report_date' => $claim->report_date->format('Y-m-d'),
            ];
        });
    }

    /**
     * GET /api/dashboard/exposure-hours
     */
    public function exposureHoursTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->exposureHours->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => ExposureHoursRepository::columns(), ...$result]]);
        }

        $paginator = $this->exposureHours->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, ExposureHoursRepository::columns(), function (Vessel $vessel) {
            $record = $vessel->latestExposureHoursRecord;

            return [
                'vessel' => $vessel->display_name,
                'date_from' => $record->date_from->format('Y-m-d'),
                'date_to' => $record->date_to->format('Y-m-d'),
                'no_of_crew' => $record->no_of_crew,
                'no_of_fat' => $record->no_of_fat,
                'no_of_ptd' => $record->no_of_ptd,
                'no_of_ppd' => $record->no_of_ppd,
                'no_of_lwc' => $record->no_of_lwc,
                'no_of_rwc' => $record->no_of_rwc,
                'no_of_mtc' => $record->no_of_mtc,
                'total_hours' => number_format((float) $record->total_hours),
            ];
        });
    }

    /**
     * GET /api/dashboard/assigned-tasks
     */
    public function assignedTasksTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->tasks->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => TaskRepository::columns(), ...$result]]);
        }

        $paginator = $this->tasks->table(TableQuery::fromRequest($request), $request->user()->id);

        return $this->tableResponse($paginator, TaskRepository::columns(), function (Task $task) {
            return [
                'task_no' => $task->task_no,
                'category' => $task->category,
                'reference_tag' => $task->reference_tag ?? '',
                'due_date' => $task->due_date->format('Y-m-d'),
                'priority' => $task->priority,
                'task_status' => $task->task_status,
            ];
        });
    }

    /**
     * GET /api/dashboard/incident-reports
     */
    public function incidentReportsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->incidentReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => IncidentReportRepository::columns(), ...$result]]);
        }

        $paginator = $this->incidentReports->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, IncidentReportRepository::columns(), function (IncidentReport $incident) {
            return [
                'record_id' => (string) $incident->id,
                'vessel' => $incident->vessel?->display_name ?? '',
                'dateof_report' => $incident->dateof_report->format('Y-m-d'),
                'nature' => $incident->nature_type === 'accident' ? 'ACCIDENT' : 'HAZARDOUS OCCURRENCE',
                'type' => $this->incidentTypeLabel($incident),
            ];
        });
    }

    /**
     * GET /api/dashboard/incident-reports/{id}
     */
    public function incidentReportDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->incidentReports->legacyDetail($id)
            : $this->incidentReports->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/company-inspections
     */
    public function companyInspectionsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->auditReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => AuditReportRepository::columns(), ...$result]]);
        }

        $paginator = $this->auditReports->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, AuditReportRepository::columns(), function (AuditReport $audit) {
            return [
                'record_id' => (string) $audit->id,
                'audit_ref' => $audit->audit_ref,
                'vessel_company' => $audit->vessel_company === 'VESSEL'
                    ? ($audit->vessel?->display_name ?? '')
                    : ($audit->company ?? ''),
                'this_date' => $audit->this_date->format('Y-m-d'),
                'nc' => "{$audit->pending_nc_count}/{$audit->total_nc_count}",
                // Observations module doesn't exist yet — see AuditReportRepository docblock.
                'obs' => '—',
            ];
        });
    }

    /**
     * GET /api/dashboard/company-inspections/{id}
     */
    public function companyInspectionDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->auditReports->legacyDetail($id)
            : $this->auditReports->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/internal-audits
     */
    public function internalAuditsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->internalAuditReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => InternalAuditReportRepository::columns(), ...$result]]);
        }

        $paginator = $this->internalAuditReports->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, InternalAuditReportRepository::columns(), function (InternalAuditReport $audit) {
            return [
                'record_id' => (string) $audit->id,
                'audit_ref' => $audit->audit_ref,
                'vessel' => $audit->vessel?->display_name ?? '',
                'this_date' => $audit->this_date->format('Y-m-d'),
                'nc' => "{$audit->pending_nc_count}/{$audit->total_nc_count}",
                'obs' => '—',
            ];
        });
    }

    /**
     * GET /api/dashboard/internal-audits/{id}
     */
    public function internalAuditDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->internalAuditReports->legacyDetail($id)
            : $this->internalAuditReports->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/psc-inspections
     */
    public function pscInspectionsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->pscReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => PscReportRepository::columns(), ...$result]]);
        }

        $paginator = $this->pscReports->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, PscReportRepository::columns(), function (PscReport $psc) {
            return [
                'record_id' => (string) $psc->id,
                'ref_no' => $psc->ref_no,
                'vessel' => $psc->vessel?->display_name ?? '',
                'date' => $psc->dateof_inspection->format('Y-m-d'),
                'nc' => "{$psc->pending_nc_count}/{$psc->total_nc_count}",
                'obs' => '—',
            ];
        });
    }

    /**
     * GET /api/dashboard/psc-inspections/{id}
     */
    public function pscInspectionDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->pscReports->legacyDetail($id)
            : $this->pscReports->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/external-audits
     */
    public function externalAuditsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->externalAuditReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => ExternalAuditReportRepository::columns(), ...$result]]);
        }

        $paginator = $this->externalAuditReports->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, ExternalAuditReportRepository::columns(), function (ExternalAuditReport $audit) {
            return [
                'record_id' => (string) $audit->id,
                'ref_no' => $audit->ref_no,
                'vessel' => $audit->vessel?->display_name ?? '',
                'date' => $audit->dateof_audit->format('Y-m-d'),
                'nc' => "{$audit->pending_nc_count}/{$audit->total_nc_count}",
                'obs' => '—',
            ];
        });
    }

    /**
     * GET /api/dashboard/external-audits/{id}
     */
    public function externalAuditDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->externalAuditReports->legacyDetail($id)
            : $this->externalAuditReports->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/risk-assessments
     */
    public function riskAssessmentsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->riskAssessments->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => RiskAssessmentRepository::columns(), ...$result]]);
        }

        $paginator = $this->riskAssessments->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, RiskAssessmentRepository::columns(), function (RiskAssessment $risk) {
            return [
                'record_id' => (string) $risk->id,
                'report_no' => $risk->report_no,
                'vessel' => $risk->vessel?->display_name ?? '',
                'risk_date' => $risk->risk_date->format('Y-m-d'),
                'category' => $risk->category_label,
                'task' => $risk->operation_label,
            ];
        });
    }

    /**
     * GET /api/dashboard/risk-assessments/{id}
     */
    public function riskAssessmentDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->riskAssessments->legacyDetail($id)
            : $this->riskAssessments->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/sire
     */
    public function sireTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->sireReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => SireReportRepository::columns(), ...$result]]);
        }

        $paginator = $this->sireReports->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, SireReportRepository::columns(), function (SireReport $sire) {
            return [
                'record_id' => (string) $sire->id,
                'vessel' => $sire->vessel?->display_name ?? '',
                'dateof_inspection' => $sire->dateof_inspection->format('Y-m-d'),
                'placeof_inspection' => $sire->placeof_inspection,
                // Observations module doesn't exist yet — see SireReportRepository docblock.
                'pending' => '—',
            ];
        });
    }

    /**
     * GET /api/dashboard/sire/{id}
     */
    public function sireDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->sireReports->legacyDetail($id)
            : $this->sireReports->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/non-sire
     */
    public function nonSireTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->nonSireReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => NonSireReportRepository::columns(), ...$result]]);
        }

        $paginator = $this->nonSireReports->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, NonSireReportRepository::columns(), function (NonSireReport $report) {
            return [
                'record_id' => (string) $report->id,
                'vessel' => $report->vessel?->display_name ?? '',
                'dateof_inspection' => $report->dateof_inspection->format('Y-m-d'),
                'placeof_inspection' => $report->placeof_inspection,
                'pending' => '—',
            ];
        });
    }

    /**
     * GET /api/dashboard/non-sire/{id}
     */
    public function nonSireDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->nonSireReports->legacyDetail($id)
            : $this->nonSireReports->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/flag-state
     */
    public function flagStateTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->flagStateReports->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => FlagStateReportRepository::columns(), ...$result]]);
        }

        $paginator = $this->flagStateReports->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, FlagStateReportRepository::columns(), function (FlagStateReport $report) {
            return [
                'record_id' => (string) $report->id,
                'ref_no' => $report->ref_no,
                'vessel' => $report->vessel?->display_name ?? '',
                'date' => $report->dateof_inspection->format('Y-m-d'),
                'nc' => "{$report->pending_nc_count}/{$report->total_nc_count}",
                'obs' => '—',
            ];
        });
    }

    /**
     * GET /api/dashboard/flag-state/{id}
     */
    public function flagStateDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->flagStateReports->legacyDetail($id)
            : $this->flagStateReports->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/committee-meetings
     */
    public function committeeMeetingsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->committeeMeetings->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => CommitteeMeetingRepository::columns(), ...$result]]);
        }

        $paginator = $this->committeeMeetings->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, CommitteeMeetingRepository::columns(), function (CommitteeMeeting $meeting) {
            return [
                'record_id' => (string) $meeting->id,
                'meeting_date' => $meeting->meeting_date->format('Y-m-d'),
                'vessel' => $meeting->vessel?->display_name ?? '',
                'type' => $meeting->meeting_types_label,
            ];
        });
    }

    /**
     * GET /api/dashboard/committee-meetings/{id}
     */
    public function committeeMeetingDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->committeeMeetings->legacyDetail($id)
            : $this->committeeMeetings->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/company-documentation
     */
    public function companyDocumentationTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->companyDocumentation->legacyTable(TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => CompanyDocumentationRepository::columns(), ...$result]]);
        }

        $paginator = $this->companyDocumentation->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, CompanyDocumentationRepository::columns(), function (CompanyDocumentationRecord $record) {
            return [
                'document' => $record->companyDocument->name,
                'date_issued' => $record->date_issued->format('Y-m-d'),
                'date_expired' => $record->date_expired?->format('Y-m-d') ?? 'Never',
                'warning' => match ($record->warning_status) {
                    2 => 'EXPIRED',
                    1 => 'EXPIRING SOON',
                    default => '',
                },
            ];
        });
    }

    /**
     * GET /api/dashboard/defects
     */
    public function defectsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->defects->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => DefectRepository::columns(), ...$result]]);
        }

        $paginator = $this->defects->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, DefectRepository::columns(), function (Defect $defect) {
            return [
                'record_id' => (string) $defect->id,
                'sl_no' => $defect->sl_no,
                'vessel' => $defect->vessel?->display_name ?? '',
                'defect_date' => $defect->defect_date->format('Y-m-d'),
                'priority' => $defect->priority,
                'category' => $defect->category,
                'compl_code' => $defect->compl_code,
            ];
        });
    }

    /**
     * GET /api/dashboard/defects/{id} — powers the dashlet's "click SL
     * No. to view" (see DefectRepository::detail()/legacyDetail()). {id}
     * is the local numeric id or, in legacy mode, the raw legacy
     * defectID string — matching whatever `record_id` the table endpoint
     * just returned for that row.
     */
    public function defectDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->defects->legacyDetail($id)
            : $this->defects->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/sms-publish-manual
     */
    public function smsPublishManualTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->manualDocumentPublish->legacyTable(TableQuery::fromRequest($request));

            return response()->json(['data' => ['columns' => ManualDocumentPublishRepository::columns(), ...$result]]);
        }

        $paginator = $this->manualDocumentPublish->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, ManualDocumentPublishRepository::columns(), function (ManualDocument $doc) {
            return [
                'chapter' => "({$doc->manualChapter->reference_no}) {$doc->manualChapter->chapter_name}",
                'manual' => "({$doc->reference_no}) {$doc->manual_name}",
                'date_of_revision' => $doc->date_of_revision->format('Y-m-d'),
            ];
        });
    }

    /**
     * GET /api/dashboard/master-reviews
     */
    public function masterReviewsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->masterReviews->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => MasterReviewRepository::columns(), ...$result]]);
        }

        $paginator = $this->masterReviews->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, MasterReviewRepository::columns(), function (MasterReview $review) {
            // manual_document_id is nullable — a review can target a whole
            // chapter with no specific procedure (see the full Master
            // Review module's docblocks).
            $reference = $review->manualDocument?->reference_no ?? $review->manualChapter?->reference_no ?? '';

            return [
                'record_id' => (string) $review->id,
                'vessel' => $review->vessel?->display_name ?? '',
                'review_date' => $review->review_date->format('Y-m-d'),
                'added_by' => $review->added_by,
                'review_quarter' => $review->review_quarter,
                'review_year' => $review->review_year,
                'sms' => trim("{$reference} ({$review->manual_section})"),
            ];
        });
    }

    /**
     * GET /api/dashboard/master-reviews/{id}
     */
    public function masterReviewDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->masterReviews->legacyDetail($id)
            : $this->masterReviews->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/isps-reviews
     */
    public function ispsReviewsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->ispsReviews->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => IspsReviewRepository::columns(), ...$result]]);
        }

        $paginator = $this->ispsReviews->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, IspsReviewRepository::columns(), function (IspsReview $review) {
            $reference = $review->manualDocument?->reference_no ?? $review->manualChapter?->reference_no ?? '';

            return [
                'record_id' => (string) $review->id,
                'vessel' => $review->vessel?->display_name ?? '',
                'review_date' => $review->review_date->format('Y-m-d'),
                'added_by' => $review->added_by,
                'review_quarter' => $review->review_quarter,
                'review_year' => $review->review_year,
                'sms' => trim("{$reference} ({$review->manual_section})"),
            ];
        });
    }

    /**
     * GET /api/dashboard/isps-reviews/{id}
     */
    public function ispsReviewDetail(string $id): JsonResponse
    {
        $detail = LegacyDb::isConfigured()
            ? $this->ispsReviews->legacyDetail($id)
            : $this->ispsReviews->detail((int) $id);

        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/dashboard/drills
     */
    public function drillsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->drills->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => DrillRepository::columns(), ...$result]]);
        }

        $paginator = $this->drills->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, DrillRepository::columns(), function (array $row) {
            return [
                'vessel' => $row['vessel']->display_name,
                'upcoming' => $row['upcoming'],
                'overdue' => $row['overdue'],
            ];
        });
    }

    /**
     * GET /api/dashboard/pms
     */
    public function pmsTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->pms->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => PmsRepository::columns(), ...$result]]);
        }

        $paginator = $this->pms->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, PmsRepository::columns(), function (array $row) {
            return [
                'vessel' => $row['vessel']->display_name,
                'upcoming' => $row['upcoming'],
                'overdue' => $row['overdue'],
                'postponed' => $row['postponed'],
            ];
        });
    }

    /**
     * GET /api/dashboard/sms-version-monitoring
     */
    public function smsVersionMonitoringTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->smsVersionMonitoring->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => SmsVersionMonitoringRepository::columns(), ...$result]]);
        }

        $paginator = $this->smsVersionMonitoring->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, SmsVersionMonitoringRepository::columns(), function (array $row) {
            return [
                'vessel' => $row['vessel']->display_name,
                'procedures' => $row['procedures'],
                'forms' => $row['forms'],
            ];
        });
    }

    /**
     * GET /api/dashboard/vessel-documentation
     */
    public function vesselDocumentationTable(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->vesselDocumentation->legacyTable(TableQuery::fromRequest($request), $request->user()?->legacy_user_id);

            return response()->json(['data' => ['columns' => VesselDocumentationRepository::columns(), ...$result]]);
        }

        $paginator = $this->vesselDocumentation->table(TableQuery::fromRequest($request));

        return $this->tableResponse($paginator, VesselDocumentationRepository::columns(), function (array $row) {
            return [
                'vessel' => $row['vessel']->display_name,
                'expiring' => $row['expiring'],
                'expired' => $row['expired'],
                'new_from_vessel' => $row['new_from_vessel'],
                'new_from_shore' => $row['new_from_shore'],
            ];
        });
    }

    /**
     * Ported from Dashboard_incident's `natureof_incidentid` column
     * formatter, minus the legacy sentinel-ID special casing (matched by
     * name instead — see IncidentReportRepository).
     */
    private function incidentTypeLabel(IncidentReport $incident): string
    {
        if ($incident->nature_type === 'accident') {
            $name = $incident->natureOfIncident?->name ?? '';

            return match ($name) {
                'Other' => trim("{$name} - {$incident->others}"),
                'Collision' => trim("{$name} - {$incident->accident_collision}"),
                default => $name,
            };
        }

        return match ($incident->hazardous_occurrence_type) {
            'unsafe_act' => 'UNSAFE ACT',
            'unsafe_condition' => 'UNSAFE CONDITION',
            'near_miss' => 'NEAR MISS',
            default => '',
        };
    }

    private function tableResponse(LengthAwarePaginator $paginator, array $columns, callable $mapRow): JsonResponse
    {
        return response()->json([
            'data' => [
                'columns' => $columns,
                'rows' => collect($paginator->items())->map($mapRow)->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }
}
