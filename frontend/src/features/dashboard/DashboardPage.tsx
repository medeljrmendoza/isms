import { useEffect, useState } from "react";
import { dashboardService } from "./dashboardService";
import type { Dashlet } from "./dashboard";
import { DashletCard } from "./DashletCard";
import { Alert } from "../../components/ui/Alert";

export function DashboardPage() {
  const [dashlets, setDashlets] = useState<Dashlet[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    dashboardService
      .getDashboard()
      .then((data) => {
        if (isMounted) setDashlets(data.dashlets);
      })
      .catch(() => {
        if (isMounted) setError("Couldn't load the dashboard. Please try again.");
      });

    return () => {
      isMounted = false;
    };
  }, []);

  return (
    // Matches every other page's width — inherits AppLayout's
    // max-w-screen-2xl directly instead of the narrower cap this used to
    // apply, so dashlet cards size like the rest of the app.
    <div>
      {error && (
        <div className="mb-4">
          <Alert variant="error">{error}</Alert>
        </div>
      )}

      {!dashlets && !error && (
        <div className="flex items-center justify-center py-24">
          <span className="h-8 w-8 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" />
        </div>
      )}

      {dashlets && (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
          {dashlets.map((dashlet) => (
            <DashletCard key={dashlet.key} dashlet={dashlet} />
          ))}
        </div>
      )}
    </div>
  );
}
