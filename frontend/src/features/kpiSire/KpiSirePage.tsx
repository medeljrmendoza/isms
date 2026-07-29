import { useEffect, useState } from "react";
import { kpiSireService } from "./kpiSireService";
import type { KpiSireOptions } from "./kpiSire";
import type { KpiSummaryRow } from "../kpi/kpi";
import { KpiBarChart } from "../kpi/KpiBarChart";
import { KpiDrillDownModal } from "../kpi/KpiDrillDownModal";
import { Button } from "../../components/ui/Button";

interface DrillDown {
  title: string;
  fetcher: Parameters<typeof KpiDrillDownModal>[0]["fetcher"];
}

/**
 * Ported from admin/kpi_sire/kpi_sire_v.php. Only "Reports per Vessel"
 * survives the migration — the other three legacy chart modes are
 * entirely Observations-based and there's no Observations module in
 * this app, so there's no report-type selector here (unlike the PSC/
 * Flag State KPI pages, which still have a genuine choice).
 */
export function KpiSirePage() {
  const [options, setOptions] = useState<KpiSireOptions>({ vessels: [] });
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
    kpiSireService.options().then(setOptions);
  }, []);

  useEffect(() => {
    setLoading(true);
    kpiSireService
      .summary({ from: appliedFrom, to: appliedTo })
      .then((data) => {
        setRows(data);
        setError(null);
      })
      .catch(() => setError("Couldn't load KPI data. Please try again."))
      .finally(() => setLoading(false));
  }, [appliedFrom, appliedTo]);

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
    const vessel = options.vessels.find((o) => o.label === row.label);
    if (!vessel) return;

    setDrillDown({
      title: `SIRE Reports — ${vessel.label}`,
      fetcher: (params) => kpiSireService.reportsByVessel(vessel.id, params),
    });
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">KPI — SIRE</h1>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
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
              title={`SIRE ${appliedFrom || appliedTo ? `(${appliedFrom ?? "…"} – ${appliedTo ?? "…"})` : `- ${new Date().getFullYear()}`}`}
              yAxisLabel="Reports"
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
