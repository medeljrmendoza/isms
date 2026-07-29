import { useEffect, useState } from "react";
import { kpiClaimsService } from "./kpiClaimsService";
import type { KpiClaimsFilter, KpiClaimsOptions } from "./kpiClaims";
import type { KpiSummaryRow } from "../kpi/kpi";
import { KpiBarChart } from "../kpi/KpiBarChart";
import { KpiDrillDownModal } from "../kpi/KpiDrillDownModal";
import { Button } from "../../components/ui/Button";

const FILTER_LABELS: Record<KpiClaimsFilter, string> = {
  vessel: "Claims per Vessel",
  category: "Claims per Category",
};

interface DrillDown {
  title: string;
  fetcher: Parameters<typeof KpiDrillDownModal>[0]["fetcher"];
}

/**
 * Ported from admin/kpi_claims/kpi_claims_v.php. Both legacy chart
 * modes are portable — unlike most other KPI modules, Kpi_claims has no
 * Observations-based chart to drop. Category has no lookup-table
 * options list (it's free-text, grouped by whatever distinct values are
 * present), so its drill-down uses the chart row's own label directly.
 */
export function KpiClaimsPage() {
  const [options, setOptions] = useState<KpiClaimsOptions>({ vessels: [] });
  const [filter, setFilter] = useState<KpiClaimsFilter>("vessel");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [appliedFrom, setAppliedFrom] = useState<string | undefined>(undefined);
  const [appliedTo, setAppliedTo] = useState<string | undefined>(undefined);

  const [rows, setRows] = useState<KpiSummaryRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [dateError, setDateError] = useState<string | null>(null);

  const [drillDown, setDrillDown] = useState<DrillDown | null>(null);

  useEffect(() => {
    kpiClaimsService.options().then(setOptions);
  }, []);

  useEffect(() => {
    setLoading(true);
    kpiClaimsService
      .summary(filter, { from: appliedFrom, to: appliedTo })
      .then((data) => {
        setRows(data);
        setError(null);
      })
      .catch(() => setError("Couldn't load KPI data. Please try again."))
      .finally(() => setLoading(false));
  }, [filter, appliedFrom, appliedTo]);

  const applyFilter = () => {
    if (from && to && from > to) {
      setDateError("Invalid date range: Date From must not be after Date To.");
      return;
    }
    setDateError(null);
    setAppliedFrom(from || undefined);
    setAppliedTo(to || undefined);
  };

  const onBarClick = (row: KpiSummaryRow) => {
    if (filter === "vessel") {
      const vessel = options.vessels.find((o) => o.label === row.label);
      if (!vessel) return;
      setDrillDown({
        title: `Claims — ${vessel.label}`,
        fetcher: (params) => kpiClaimsService.byVessel(vessel.id, params),
      });
    } else {
      setDrillDown({
        title: `Claims — ${row.label}`,
        fetcher: (params) => kpiClaimsService.byCategory(row.label, params),
      });
    }
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">KPI — Claims</h1>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Report</label>
            <select
              value={filter}
              onChange={(e) => setFilter(e.target.value as KpiClaimsFilter)}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            >
              {Object.entries(FILTER_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Date From</label>
            <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm" />
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Date To</label>
            <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm" />
          </div>
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={applyFilter}>
            Filter
          </Button>
          {dateError && <p className="text-sm text-red-600">{dateError}</p>}
        </div>

        <div className="px-4 py-4">
          {loading && (
            <div className="flex items-center gap-2 py-8 justify-center text-xs text-slate-400">
              <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" />
              Loading...
            </div>
          )}
          {error && <p className="text-sm text-red-600">{error}</p>}
          {!loading && !error && (
            <KpiBarChart
              title={`Claims ${appliedFrom || appliedTo ? `(${appliedFrom ?? "…"} – ${appliedTo ?? "…"})` : `- ${new Date().getFullYear()}`}`}
              yAxisLabel="Claims"
              rows={rows}
              onBarClick={onBarClick}
            />
          )}
        </div>
      </div>

      {drillDown && (
        <KpiDrillDownModal title={drillDown.title} from={appliedFrom} to={appliedTo} fetcher={drillDown.fetcher} onClose={() => setDrillDown(null)} />
      )}
    </div>
  );
}
