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
