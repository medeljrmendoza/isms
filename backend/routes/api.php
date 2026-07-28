<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyInspectionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\IncidentReportController;
use App\Http\Controllers\Api\NonconformityController;
use App\Http\Controllers\Api\PscReportController;
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
});
