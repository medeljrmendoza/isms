import { useEffect, useState } from "react";
import { kpiFlagStateService } from "./kpiFlagStateService";
import type { KpiFlagStateFilter, KpiFlagStateOptions } from "./kpiFlagState";
import type { KpiOption, KpiSummaryRow } from "../kpi/kpi";
import { KpiBarChart } from "../kpi/KpiBarChart";
import { KpiDrillDownModal } from "../kpi/KpiDrillDownModal";
import { Button } from "../../components/ui/Button";

const FILTER_LABELS: Record<KpiFlagStateFilter, string> = {
  vessel: "Reports per Vessel",
  nonconformities: "Non Conformities per Vessel",
};

interface DrillDown {
  title: string;
  fetcher: Parameters<typeof KpiDrillDownModal>[0]["fetcher"];
}

/** Ported from admin/kpi_flag_state/kpi_flag_state_v.php. */
export function KpiFlagStatePage() {
  const [options, setOptions] = useState<KpiFlagStateOptions>({ vessels: [] });
  const [filter, setFilter] = useState<KpiFlagStateFilter>("vessel");
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
    kpiFlagStateService.options().then(setOptions);
  }, []);

  useEffect(() => {
    setLoading(true);
    kpiFlagStateService
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

  const findOption = (list: KpiOption[], label: string) => list.find((o) => o.label === label);

  const onBarClick = (row: KpiSummaryRow) => {
    const vessel = findOption(options.vessels, row.label);
    if (!vessel) return;

    if (filter === "vessel") {
      setDrillDown({
        title: `Flag State Reports — ${vessel.label}`,
        fetcher: (params) => kpiFlagStateService.reportsByVessel(vessel.id, params),
      });
    } else {
      setDrillDown({
        title: `Non Conformities — ${vessel.label}`,
        fetcher: (params) => kpiFlagStateService.nonConformitiesByVessel(vessel.id, params),
      });
    }
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">KPI — Flag State</h1>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Report</label>
            <select
              value={filter}
              onChange={(e) => setFilter(e.target.value as KpiFlagStateFilter)}
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
              title={`Flag State ${appliedFrom || appliedTo ? `(${appliedFrom ?? "…"} – ${appliedTo ?? "…"})` : `- ${new Date().getFullYear()}`}`}
              yAxisLabel={filter === "nonconformities" ? "Non Conformities" : "Reports"}
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
