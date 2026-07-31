<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Claims\KpiClaimsController;
use App\Http\Controllers\Api\CommitteeMeetings\CommitteeMeetingController;
use App\Http\Controllers\Api\CompanyDocumentation\CompanyDocumentationController;
use App\Http\Controllers\Api\CompanyInspections\CompanyInspectionController;
use App\Http\Controllers\Api\CompanyInspections\KpiCompanyInspectionsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Drills\DrillReportController;
use App\Http\Controllers\Api\ExposureHours\ExposureHoursController;
use App\Http\Controllers\Api\ExternalAudits\ExternalAuditController;
use App\Http\Controllers\Api\FlagState\FlagStateReportController;
use App\Http\Controllers\Api\FlagState\KpiFlagStateController;
use App\Http\Controllers\Api\IncidentReports\IncidentReportController;
use App\Http\Controllers\Api\InternalAudits\InternalAuditController;
use App\Http\Controllers\Api\InternalAudits\KpiInternalAuditsController;
use App\Http\Controllers\Api\IspsReview\IspsReviewController;
use App\Http\Controllers\Api\ManualBrowser\ManualBrowserController;
use App\Http\Controllers\Api\MasterReview\MasterReviewController;
use App\Http\Controllers\Api\Nonconformities\NonconformityController;
use App\Http\Controllers\Api\NonSire\KpiNonSireController;
use App\Http\Controllers\Api\NonSire\NonSireReportController;
use App\Http\Controllers\Api\Pms\PmsRunningHoursController;
use App\Http\Controllers\Api\PscReports\KpiPscInspectionsController;
use App\Http\Controllers\Api\PscReports\PscReportController;
use App\Http\Controllers\Api\RevisionHistory\RevisionHistoryController;
use App\Http\Controllers\Api\RiskAssessment\RiskAssessmentController;
use App\Http\Controllers\Api\RiskAssessment\RiskAssessmentShoreController;
use App\Http\Controllers\Api\Sire\KpiSireController;
use App\Http\Controllers\Api\Sire\SireReportController;
use App\Http\Controllers\Api\VesselDocumentation\VesselDocumentationController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/nonconformities', [DashboardController::class, 'nonconformitiesTable']);
    Route::get('/dashboard/claims', [DashboardController::class, 'claimsTable']);
    Route::get('/dashboard/exposure-hours', [DashboardController::class, 'exposureHoursTable']);
    Route::get('/dashboard/assigned-tasks', [DashboardController::class, 'assignedTasksTable']);
    Route::get('/dashboard/incident-reports', [DashboardController::class, 'incidentReportsTable']);
    Route::get('/dashboard/company-inspections', [DashboardController::class, 'companyInspectionsTable']);
    Route::get('/dashboard/internal-audits', [DashboardController::class, 'internalAuditsTable']);
    Route::get('/dashboard/psc-inspections', [DashboardController::class, 'pscInspectionsTable']);
    Route::get('/dashboard/external-audits', [DashboardController::class, 'externalAuditsTable']);
    Route::get('/dashboard/risk-assessments', [DashboardController::class, 'riskAssessmentsTable']);
    Route::get('/dashboard/sire', [DashboardController::class, 'sireTable']);
    Route::get('/dashboard/non-sire', [DashboardController::class, 'nonSireTable']);
    Route::get('/dashboard/flag-state', [DashboardController::class, 'flagStateTable']);
    Route::get('/dashboard/committee-meetings', [DashboardController::class, 'committeeMeetingsTable']);
    Route::get('/dashboard/company-documentation', [DashboardController::class, 'companyDocumentationTable']);
    Route::get('/dashboard/defects', [DashboardController::class, 'defectsTable']);
    Route::get('/dashboard/sms-publish-manual', [DashboardController::class, 'smsPublishManualTable']);
    Route::get('/dashboard/master-reviews', [DashboardController::class, 'masterReviewsTable']);
    Route::get('/dashboard/isps-reviews', [DashboardController::class, 'ispsReviewsTable']);
    Route::get('/dashboard/drills', [DashboardController::class, 'drillsTable']);
    Route::get('/dashboard/pms', [DashboardController::class, 'pmsTable']);
    Route::get('/dashboard/sms-version-monitoring', [DashboardController::class, 'smsVersionMonitoringTable']);
    Route::get('/dashboard/vessel-documentation', [DashboardController::class, 'vesselDocumentationTable']);
    Route::get('/notifications/counts', [DashboardController::class, 'notificationCounts']);

    Route::get('/nonconformities/options', [NonconformityController::class, 'options']);
    Route::get('/nonconformities', [NonconformityController::class, 'index']);
    Route::post('/nonconformities', [NonconformityController::class, 'store']);
    Route::get('/nonconformities/{nonconformity}', [NonconformityController::class, 'show']);
    Route::put('/nonconformities/{nonconformity}', [NonconformityController::class, 'update']);
    Route::delete('/nonconformities/{nonconformity}', [NonconformityController::class, 'destroy']);
    Route::post('/nonconformities/{nonconformity}/publish', [NonconformityController::class, 'publish']);
    Route::post('/nonconformities/{nonconformity}/approve', [NonconformityController::class, 'approve']);
    Route::post('/nonconformities/{nonconformity}/reopen', [NonconformityController::class, 'reopen']);

    Route::get('/incident-reports/options', [IncidentReportController::class, 'options']);
    Route::get('/incident-reports', [IncidentReportController::class, 'index']);
    Route::post('/incident-reports', [IncidentReportController::class, 'store']);
    Route::get('/incident-reports/{incidentReport}', [IncidentReportController::class, 'show']);
    Route::put('/incident-reports/{incidentReport}', [IncidentReportController::class, 'update']);
    Route::delete('/incident-reports/{incidentReport}', [IncidentReportController::class, 'destroy']);
    Route::post('/incident-reports/{incidentReport}/publish', [IncidentReportController::class, 'publish']);
    Route::post('/incident-reports/{incidentReport}/approve', [IncidentReportController::class, 'approve']);
    Route::post('/incident-reports/{incidentReport}/reopen', [IncidentReportController::class, 'reopen']);

    Route::get('/psc-reports/options', [PscReportController::class, 'options']);
    Route::get('/psc-reports', [PscReportController::class, 'index']);
    Route::post('/psc-reports', [PscReportController::class, 'store']);
    Route::get('/psc-reports/{pscReport}', [PscReportController::class, 'show']);
    Route::put('/psc-reports/{pscReport}', [PscReportController::class, 'update']);
    Route::delete('/psc-reports/{pscReport}', [PscReportController::class, 'destroy']);
    Route::post('/psc-reports/{pscReport}/reopen', [PscReportController::class, 'reopen']);

    Route::get('/company-inspections/options', [CompanyInspectionController::class, 'options']);
    Route::get('/company-inspections', [CompanyInspectionController::class, 'index']);
    Route::post('/company-inspections', [CompanyInspectionController::class, 'store']);
    Route::get('/company-inspections/{auditReport}', [CompanyInspectionController::class, 'show']);
    Route::put('/company-inspections/{auditReport}', [CompanyInspectionController::class, 'update']);
    Route::delete('/company-inspections/{auditReport}', [CompanyInspectionController::class, 'destroy']);

    Route::get('/internal-audits/options', [InternalAuditController::class, 'options']);
    Route::get('/internal-audits', [InternalAuditController::class, 'index']);
    Route::post('/internal-audits', [InternalAuditController::class, 'store']);
    Route::get('/internal-audits/{internalAuditReport}', [InternalAuditController::class, 'show']);
    Route::put('/internal-audits/{internalAuditReport}', [InternalAuditController::class, 'update']);
    Route::delete('/internal-audits/{internalAuditReport}', [InternalAuditController::class, 'destroy']);

    Route::get('/external-audits/options', [ExternalAuditController::class, 'options']);
    Route::get('/external-audits', [ExternalAuditController::class, 'index']);
    Route::post('/external-audits', [ExternalAuditController::class, 'store']);
    Route::get('/external-audits/{externalAuditReport}', [ExternalAuditController::class, 'show']);
    Route::put('/external-audits/{externalAuditReport}', [ExternalAuditController::class, 'update']);
    Route::delete('/external-audits/{externalAuditReport}', [ExternalAuditController::class, 'destroy']);
    Route::post('/external-audits/{externalAuditReport}/publish', [ExternalAuditController::class, 'publish']);
    Route::post('/external-audits/{externalAuditReport}/approve', [ExternalAuditController::class, 'approve']);

    Route::get('/sire-reports/options', [SireReportController::class, 'options']);
    Route::get('/sire-reports', [SireReportController::class, 'index']);
    Route::post('/sire-reports', [SireReportController::class, 'store']);
    Route::get('/sire-reports/{sireReport}', [SireReportController::class, 'show']);
    Route::put('/sire-reports/{sireReport}', [SireReportController::class, 'update']);
    Route::delete('/sire-reports/{sireReport}', [SireReportController::class, 'destroy']);
    Route::post('/sire-reports/{sireReport}/publish', [SireReportController::class, 'publish']);
    Route::post('/sire-reports/{sireReport}/approve', [SireReportController::class, 'approve']);

    Route::get('/non-sire-reports/options', [NonSireReportController::class, 'options']);
    Route::get('/non-sire-reports', [NonSireReportController::class, 'index']);
    Route::post('/non-sire-reports', [NonSireReportController::class, 'store']);
    Route::get('/non-sire-reports/{nonSireReport}', [NonSireReportController::class, 'show']);
    Route::put('/non-sire-reports/{nonSireReport}', [NonSireReportController::class, 'update']);
    Route::delete('/non-sire-reports/{nonSireReport}', [NonSireReportController::class, 'destroy']);
    Route::post('/non-sire-reports/{nonSireReport}/publish', [NonSireReportController::class, 'publish']);
    Route::post('/non-sire-reports/{nonSireReport}/approve', [NonSireReportController::class, 'approve']);

    Route::get('/flag-state-reports/options', [FlagStateReportController::class, 'options']);
    Route::get('/flag-state-reports', [FlagStateReportController::class, 'index']);
    Route::post('/flag-state-reports', [FlagStateReportController::class, 'store']);
    Route::get('/flag-state-reports/{flagStateReport}', [FlagStateReportController::class, 'show']);
    Route::put('/flag-state-reports/{flagStateReport}', [FlagStateReportController::class, 'update']);
    Route::delete('/flag-state-reports/{flagStateReport}', [FlagStateReportController::class, 'destroy']);
    Route::post('/flag-state-reports/{flagStateReport}/publish', [FlagStateReportController::class, 'publish']);
    Route::post('/flag-state-reports/{flagStateReport}/approve', [FlagStateReportController::class, 'approve']);

    Route::get('/committee-meetings/options', [CommitteeMeetingController::class, 'options']);
    Route::get('/committee-meetings', [CommitteeMeetingController::class, 'index']);
    Route::post('/committee-meetings', [CommitteeMeetingController::class, 'store']);
    Route::get('/committee-meetings/{committeeMeeting}', [CommitteeMeetingController::class, 'show']);
    Route::put('/committee-meetings/{committeeMeeting}', [CommitteeMeetingController::class, 'update']);
    Route::delete('/committee-meetings/{committeeMeeting}', [CommitteeMeetingController::class, 'destroy']);
    Route::post('/committee-meetings/{committeeMeeting}/publish', [CommitteeMeetingController::class, 'publish']);
    Route::post('/committee-meetings/{committeeMeeting}/approve', [CommitteeMeetingController::class, 'approve']);

    Route::get('/drill-lists/options', [DrillReportController::class, 'options']);
    Route::get('/drill-lists/calendar', [DrillReportController::class, 'calendar']);
    Route::get('/drill-reports', [DrillReportController::class, 'cell']);
    Route::get('/drill-reports/{drillReport}', [DrillReportController::class, 'show']);
    Route::put('/drill-reports/{drillReport}', [DrillReportController::class, 'update']);

    Route::get('/exposure-hours/options', [ExposureHoursController::class, 'options']);
    Route::get('/exposure-hours/summary', [ExposureHoursController::class, 'summary']);
    Route::get('/exposure-hours-records', [ExposureHoursController::class, 'index']);
    Route::post('/exposure-hours-records', [ExposureHoursController::class, 'store']);
    Route::get('/exposure-hours-records/{exposureHoursRecord}', [ExposureHoursController::class, 'show']);
    Route::put('/exposure-hours-records/{exposureHoursRecord}', [ExposureHoursController::class, 'update']);
    Route::delete('/exposure-hours-records/{exposureHoursRecord}', [ExposureHoursController::class, 'destroy']);

    Route::get('/risk-assessments/options', [RiskAssessmentController::class, 'options']);
    Route::get('/risk-assessments', [RiskAssessmentController::class, 'index']);
    Route::get('/risk-assessments/{riskAssessment}', [RiskAssessmentController::class, 'show']);
    Route::post('/risk-assessments/{riskAssessment}/approve-shore', [RiskAssessmentController::class, 'approveShore']);
    Route::post('/risk-assessments/{riskAssessment}/approve-marine', [RiskAssessmentController::class, 'approveMarine']);

    Route::get('/risk-assessments-shore/options', [RiskAssessmentShoreController::class, 'options']);
    Route::get('/risk-assessments-shore', [RiskAssessmentShoreController::class, 'index']);
    Route::post('/risk-assessments-shore', [RiskAssessmentShoreController::class, 'store']);
    Route::get('/risk-assessments-shore/{riskAssessmentShore}', [RiskAssessmentShoreController::class, 'show']);
    Route::put('/risk-assessments-shore/{riskAssessmentShore}', [RiskAssessmentShoreController::class, 'update']);
    Route::delete('/risk-assessments-shore/{riskAssessmentShore}', [RiskAssessmentShoreController::class, 'destroy']);
    Route::post('/risk-assessments-shore/{riskAssessmentShore}/reopen', [RiskAssessmentShoreController::class, 'reopen']);

    Route::get('/kpi/psc-inspections/options', [KpiPscInspectionsController::class, 'options']);
    Route::get('/kpi/psc-inspections/summary', [KpiPscInspectionsController::class, 'summary']);
    Route::get('/kpi/psc-inspections/reports-by-vessel', [KpiPscInspectionsController::class, 'reportsByVessel']);
    Route::get('/kpi/psc-inspections/reports-by-mou', [KpiPscInspectionsController::class, 'reportsByMou']);
    Route::get('/kpi/psc-inspections/nonconformities-by-vessel', [KpiPscInspectionsController::class, 'nonConformitiesByVessel']);

    Route::get('/kpi/flag-state/options', [KpiFlagStateController::class, 'options']);
    Route::get('/kpi/flag-state/summary', [KpiFlagStateController::class, 'summary']);
    Route::get('/kpi/flag-state/reports-by-vessel', [KpiFlagStateController::class, 'reportsByVessel']);
    Route::get('/kpi/flag-state/nonconformities-by-vessel', [KpiFlagStateController::class, 'nonConformitiesByVessel']);

    Route::get('/kpi/sire/options', [KpiSireController::class, 'options']);
    Route::get('/kpi/sire/summary', [KpiSireController::class, 'summary']);
    Route::get('/kpi/sire/reports-by-vessel', [KpiSireController::class, 'reportsByVessel']);

    Route::get('/kpi/non-sire/options', [KpiNonSireController::class, 'options']);
    Route::get('/kpi/non-sire/summary', [KpiNonSireController::class, 'summary']);
    Route::get('/kpi/non-sire/reports-by-vessel', [KpiNonSireController::class, 'reportsByVessel']);
    Route::get('/kpi/non-sire/reports-by-inspection-type', [KpiNonSireController::class, 'reportsByInspectionType']);

    Route::get('/kpi/company-inspections/options', [KpiCompanyInspectionsController::class, 'options']);
    Route::get('/kpi/company-inspections/summary', [KpiCompanyInspectionsController::class, 'summary']);
    Route::get('/kpi/company-inspections/reports-by-vessel', [KpiCompanyInspectionsController::class, 'reportsByVessel']);
    Route::get('/kpi/company-inspections/reports-by-company', [KpiCompanyInspectionsController::class, 'reportsByCompany']);
    Route::get('/kpi/company-inspections/nonconformities-by-vessel', [KpiCompanyInspectionsController::class, 'nonConformitiesByVessel']);
    Route::get('/kpi/company-inspections/nonconformities-by-company', [KpiCompanyInspectionsController::class, 'nonConformitiesByCompany']);

    Route::get('/kpi/internal-audits/options', [KpiInternalAuditsController::class, 'options']);
    Route::get('/kpi/internal-audits/summary', [KpiInternalAuditsController::class, 'summary']);
    Route::get('/kpi/internal-audits/reports-by-vessel', [KpiInternalAuditsController::class, 'reportsByVessel']);
    Route::get('/kpi/internal-audits/nonconformities-by-vessel', [KpiInternalAuditsController::class, 'nonConformitiesByVessel']);

    Route::get('/kpi/claims/options', [KpiClaimsController::class, 'options']);
    Route::get('/kpi/claims/summary', [KpiClaimsController::class, 'summary']);
    Route::get('/kpi/claims/by-vessel', [KpiClaimsController::class, 'byVessel']);
    Route::get('/kpi/claims/by-category', [KpiClaimsController::class, 'byCategory']);

    Route::get('/vessel-documentation/options', [VesselDocumentationController::class, 'options']);
    Route::get('/vessel-documentation/type-options', [VesselDocumentationController::class, 'typeOptions']);
    Route::get('/vessel-documentation/document-options', [VesselDocumentationController::class, 'documentOptions']);
    Route::get('/vessel-documentation', [VesselDocumentationController::class, 'index']);
    Route::post('/vessel-documentation', [VesselDocumentationController::class, 'store']);
    Route::get('/vessel-documentation/{vesselDocumentRecord}', [VesselDocumentationController::class, 'show']);
    Route::put('/vessel-documentation/{vesselDocumentRecord}', [VesselDocumentationController::class, 'update']);
    Route::post('/vessel-documentation/{vesselDocumentRecord}/toggle-status', [VesselDocumentationController::class, 'toggleStatus']);
    Route::delete('/vessel-documentation/{vesselDocumentRecord}', [VesselDocumentationController::class, 'destroy']);

    Route::get('/company-documentation/type-options', [CompanyDocumentationController::class, 'typeOptions']);
    Route::get('/company-documentation/document-options', [CompanyDocumentationController::class, 'documentOptions']);
    Route::get('/company-documentation', [CompanyDocumentationController::class, 'index']);
    Route::post('/company-documentation', [CompanyDocumentationController::class, 'store']);
    Route::get('/company-documentation/{companyDocumentationRecord}', [CompanyDocumentationController::class, 'show']);
    Route::put('/company-documentation/{companyDocumentationRecord}', [CompanyDocumentationController::class, 'update']);
    Route::post('/company-documentation/{companyDocumentationRecord}/toggle-status', [CompanyDocumentationController::class, 'toggleStatus']);
    Route::delete('/company-documentation/{companyDocumentationRecord}', [CompanyDocumentationController::class, 'destroy']);

    Route::get('/master-review/options', [MasterReviewController::class, 'options']);
    Route::get('/master-review/document-options', [MasterReviewController::class, 'documentOptions']);
    Route::get('/master-review', [MasterReviewController::class, 'index']);
    Route::post('/master-review', [MasterReviewController::class, 'store']);
    Route::get('/master-review/{masterReview}', [MasterReviewController::class, 'show']);
    Route::put('/master-review/{masterReview}', [MasterReviewController::class, 'update']);
    Route::post('/master-review/{masterReview}/approve', [MasterReviewController::class, 'approve']);
    Route::post('/master-review/{masterReview}/disapprove', [MasterReviewController::class, 'disapprove']);
    Route::post('/master-review/{masterReview}/disregard', [MasterReviewController::class, 'disregard']);
    Route::post('/master-review/{masterReview}/recommend-approval', [MasterReviewController::class, 'recommendApproval']);
    Route::post('/master-review/{masterReview}/under-review', [MasterReviewController::class, 'underReview']);
    Route::post('/master-review/{masterReview}/reopen', [MasterReviewController::class, 'reopen']);
    Route::delete('/master-review/{masterReview}', [MasterReviewController::class, 'destroy']);

    Route::get('/pms-running-hours/options', [PmsRunningHoursController::class, 'options']);
    Route::get('/pms-running-hours', [PmsRunningHoursController::class, 'index']);
    Route::post('/pms-running-hours/update', [PmsRunningHoursController::class, 'update']);
    Route::post('/pms-running-hours/proceed-next-month', [PmsRunningHoursController::class, 'proceedNextMonth']);

    Route::get('/manuals/options', [ManualBrowserController::class, 'options']);
    Route::get('/manuals/tree', [ManualBrowserController::class, 'tree']);
    Route::get('/manuals/search', [ManualBrowserController::class, 'search']);

    Route::get('/isps-review/options', [IspsReviewController::class, 'options']);
    Route::get('/isps-review/document-options', [IspsReviewController::class, 'documentOptions']);
    Route::get('/isps-review', [IspsReviewController::class, 'index']);
    Route::post('/isps-review', [IspsReviewController::class, 'store']);
    Route::get('/isps-review/{ispsReview}', [IspsReviewController::class, 'show']);
    Route::put('/isps-review/{ispsReview}', [IspsReviewController::class, 'update']);
    Route::post('/isps-review/{ispsReview}/approve', [IspsReviewController::class, 'approve']);
    Route::post('/isps-review/{ispsReview}/disapprove', [IspsReviewController::class, 'disapprove']);
    Route::post('/isps-review/{ispsReview}/disregard', [IspsReviewController::class, 'disregard']);
    Route::post('/isps-review/{ispsReview}/recommend-approval', [IspsReviewController::class, 'recommendApproval']);
    Route::post('/isps-review/{ispsReview}/reopen', [IspsReviewController::class, 'reopen']);
    Route::delete('/isps-review/{ispsReview}', [IspsReviewController::class, 'destroy']);

    Route::get('/revision-history/options', [RevisionHistoryController::class, 'options']);
    Route::get('/revision-history/document-options', [RevisionHistoryController::class, 'documentOptions']);
    Route::get('/revision-history', [RevisionHistoryController::class, 'index']);
    Route::post('/revision-history', [RevisionHistoryController::class, 'store']);
    Route::get('/revision-history/{manualRevision}', [RevisionHistoryController::class, 'show']);
    Route::put('/revision-history/{manualRevision}', [RevisionHistoryController::class, 'update']);
    Route::delete('/revision-history/{manualRevision}', [RevisionHistoryController::class, 'destroy']);
});
