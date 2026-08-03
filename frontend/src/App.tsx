import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom";
import { AuthProvider } from "./context/AuthContext";
import { ProtectedRoute } from "./components/ProtectedRoute";
import { AppLayout } from "./components/layout/AppLayout";
import { LoginPage } from "./features/auth/LoginPage";
import { DashboardPage } from "./features/dashboard/DashboardPage";
import { NonconformitiesPage } from "./features/nonconformities/NonconformitiesPage";
import { IncidentReportsPage } from "./features/incidentReports/IncidentReportsPage";
import { PscReportsPage } from "./features/pscReports/PscReportsPage";
import { CompanyInspectionsPage } from "./features/companyInspections/CompanyInspectionsPage";
import { InternalAuditsPage } from "./features/internalAudits/InternalAuditsPage";
import { ExternalAuditsPage } from "./features/externalAudits/ExternalAuditsPage";
import { SireReportsPage } from "./features/sire/SireReportsPage";
import { NonSireReportsPage } from "./features/nonSire/NonSireReportsPage";
import { FlagStateReportsPage } from "./features/flagState/FlagStateReportsPage";
import { CommitteeMeetingsPage } from "./features/committeeMeetings/CommitteeMeetingsPage";
import { DrillCalendarPage } from "./features/drills/DrillCalendarPage";
import { ExposureHoursSummaryPage } from "./features/exposureHours/ExposureHoursSummaryPage";
import { ExposureHoursRecordsPage } from "./features/exposureHours/ExposureHoursRecordsPage";
import { RiskAssessmentListPage } from "./features/riskAssessment/RiskAssessmentListPage";
import { RiskAssessmentShoreListPage } from "./features/riskAssessmentShore/RiskAssessmentShoreListPage";
import { KpiPscInspectionsPage } from "./features/kpiPscInspections/KpiPscInspectionsPage";
import { KpiFlagStatePage } from "./features/kpiFlagState/KpiFlagStatePage";
import { KpiSirePage } from "./features/kpiSire/KpiSirePage";
import { KpiNonSirePage } from "./features/kpiNonSire/KpiNonSirePage";
import { KpiCompanyInspectionsPage } from "./features/kpiCompanyInspections/KpiCompanyInspectionsPage";
import { KpiSireVsCompanyInspectionsPage } from "./features/kpiSireVsCompanyInspections/KpiSireVsCompanyInspectionsPage";
import { KpiInternalAuditsPage } from "./features/kpiInternalAudits/KpiInternalAuditsPage";
import { KpiClaimsPage } from "./features/kpiClaims/KpiClaimsPage";
import { VesselDocumentationPage } from "./features/vesselDocumentation/VesselDocumentationPage";
import { CompanyDocumentationPage } from "./features/companyDocumentation/CompanyDocumentationPage";
import { MasterReviewPage } from "./features/masterReview/MasterReviewPage";
import { RevisionHistoryPage } from "./features/revisionHistory/RevisionHistoryPage";
import { IspsReviewPage } from "./features/ispsReview/IspsReviewPage";
import { ManualsPage } from "./features/manuals/ManualsPage";
import { PmsRunningHoursPage } from "./features/pmsRunningHours/PmsRunningHoursPage";
import { DefectsPage } from "./features/defects/DefectsPage";
import { PmsActivitiesPage } from "./features/pmsActivities/PmsActivitiesPage";
import { PmsDoneActivitiesPage } from "./features/pmsDoneActivities/PmsDoneActivitiesPage";
import { PmsWorkPlanPage } from "./features/pmsWorkPlan/PmsWorkPlanPage";
import { ComingSoonPage } from "./pages/ComingSoonPage";

// Placeholder — swap in the real page once the change-password module is migrated.
function ChangePasswordPage() {
  return <div className="p-6">Change password</div>;
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />

          <Route element={<ProtectedRoute />}>
            <Route element={<AppLayout />}>
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route path="/dashboard" element={<DashboardPage />} />
              <Route path="/nonconformities" element={<NonconformitiesPage />} />
              <Route path="/incident" element={<IncidentReportsPage />} />
              <Route path="/psc" element={<PscReportsPage />} />
              <Route path="/company" element={<CompanyInspectionsPage />} />
              <Route path="/internal" element={<InternalAuditsPage />} />
              <Route path="/external" element={<ExternalAuditsPage />} />
              <Route path="/sire" element={<SireReportsPage />} />
              <Route path="/non_sire" element={<NonSireReportsPage />} />
              <Route path="/flag_state" element={<FlagStateReportsPage />} />
              <Route path="/committee_meeting" element={<CommitteeMeetingsPage />} />
              <Route path="/drill/calendar" element={<DrillCalendarPage />} />
              <Route path="/exposure_hours" element={<ExposureHoursSummaryPage />} />
              <Route path="/exposure_hours/:vesselId" element={<ExposureHoursRecordsPage />} />
              <Route path="/risk_assessment" element={<RiskAssessmentListPage />} />
              <Route path="/risk_assessment_shore" element={<RiskAssessmentShoreListPage />} />
              <Route path="/kpi_psc_inspections" element={<KpiPscInspectionsPage />} />
              <Route path="/kpi_flag_state" element={<KpiFlagStatePage />} />
              <Route path="/kpi_sire" element={<KpiSirePage />} />
              <Route path="/kpi_non_sire" element={<KpiNonSirePage />} />
              <Route path="/kpi_company_inspections" element={<KpiCompanyInspectionsPage />} />
              <Route path="/kpi_sire_vs_company_inspection" element={<KpiSireVsCompanyInspectionsPage />} />
              <Route path="/kpi_internal" element={<KpiInternalAuditsPage />} />
              <Route path="/kpi_claims" element={<KpiClaimsPage />} />
              <Route path="/vessel_documentation" element={<VesselDocumentationPage />} />
              <Route path="/company_documentation" element={<CompanyDocumentationPage />} />
              <Route path="/master_review" element={<MasterReviewPage />} />
              <Route path="/sms_revision" element={<RevisionHistoryPage />} />
              <Route path="/isps_review" element={<IspsReviewPage />} />
              <Route path="/sms" element={<ManualsPage />} />
              <Route path="/pms_running_hours_equipments" element={<PmsRunningHoursPage />} />
              <Route path="/defect_list" element={<DefectsPage />} />
              <Route path="/pms_activities" element={<PmsActivitiesPage />} />
              <Route path="/pms_work_plan" element={<PmsWorkPlanPage />} />
              <Route path="/pms_done_activities" element={<PmsDoneActivitiesPage />} />
              <Route path="/change-password" element={<ChangePasswordPage />} />
              {/* Every other nav link points at a real legacy route that
                  isn't migrated yet — see src/data/navigation.ts */}
              <Route path="*" element={<ComingSoonPage />} />
            </Route>
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
