import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom";
import { AuthProvider } from "./context/AuthContext";
import { ProtectedRoute } from "./components/ProtectedRoute";
import { AppLayout } from "./components/layout/AppLayout";
import { LoginPage } from "./pages/LoginPage";
import { DashboardPage } from "./pages/DashboardPage";
import { NonconformitiesPage } from "./pages/NonconformitiesPage";
import { IncidentReportsPage } from "./pages/IncidentReportsPage";
import { PscReportsPage } from "./pages/PscReportsPage";
import { CompanyInspectionsPage } from "./pages/CompanyInspectionsPage";
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
