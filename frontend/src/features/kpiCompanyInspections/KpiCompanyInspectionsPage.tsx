import { useEffect, useState } from "react";
import { kpiCompanyInspectionsService } from "./kpiCompanyInspectionsService";
import type { KpiCompanyInspectionsFilter, KpiCompanyInspectionsOptions } from "./kpiCompanyInspections";
import type { KpiSummaryRow } from "../kpi/kpi";
import { KpiBarChart } from "../kpi/KpiBarChart";
import { KpiDrillDownModal } from "../kpi/KpiDrillDownModal";
import { Button } from "../../components/ui/Button";

const FILTER_LABELS: Record<KpiCompanyInspectionsFilter, string> = {
  vessel: "Reports per Vessel",
  company: "Reports per Company",
  nc_vessel: "Non Conformities per Vessel",
  nc_company: "Non Conformities per Company",
};

interface DrillDown {
  title: string;
  fetcher: Parameters<typeof KpiDrillDownModal>[0]["fetcher"];
}

/**
 * Ported from admin/kpi_company_inspections/kpi_company_inspections_v.php.
 * Four of the six legacy chart modes survive — Reports per Vessel,
 * Reports per Company, Non Conformities per Vessel, Non Conformities
 * per Company. Observations per Vessel/Company are dropped — no
 * Observations module exists anywhere in this migration. "Company" has
 * no lookup-table options list (it's the free-text AuditReport.company
 * column scoped to vessel_company = 'COMPANY'), so its drill-down uses
 * the chart row's own label directly, same as Non-SIRE's inspection type.
 */
export function KpiCompanyInspectionsPage() {
  const [options, setOptions] = useState<KpiCompanyInspectionsOptions>({ vessels: [] });
  const [filter, setFilter] = useState<KpiCompanyInspectionsFilter>("vessel");
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
    kpiCompanyInspectionsService.options().then(setOptions);
  }, []);

  useEffect(() => {
    setLoading(true);
    kpiCompanyInspectionsService
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
    if (filter === "vessel" || filter === "nc_vessel") {
      const vessel = options.vessels.find((o) => o.label === row.label);
      if (!vessel) return;
      setDrillDown(
        filter === "vessel"
          ? { title: `Company Inspection Reports — ${vessel.label}`, fetcher: (params) => kpiCompanyInspectionsService.reportsByVessel(vessel.id, params) }
          : { title: `Non Conformities — ${vessel.label}`, fetcher: (params) => kpiCompanyInspectionsService.nonConformitiesByVessel(vessel.id, params) },
      );
    } else {
      setDrillDown(
        filter === "company"
          ? { title: `Company Inspection Reports — ${row.label}`, fetcher: (params) => kpiCompanyInspectionsService.reportsByCompany(row.label, params) }
          : { title: `Non Conformities — ${row.label}`, fetcher: (params) => kpiCompanyInspectionsService.nonConformitiesByCompany(row.label, params) },
      );
    }
  };

  const isNc = filter === "nc_vessel" || filter === "nc_company";

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">KPI — Company Inspections</h1>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Report</label>
            <select
              value={filter}
              onChange={(e) => setFilter(e.target.value as KpiCompanyInspectionsFilter)}
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
              title={`Company Inspections ${appliedFrom || appliedTo ? `(${appliedFrom ?? "…"} – ${appliedTo ?? "…"})` : `- ${new Date().getFullYear()}`}`}
              yAxisLabel={isNc ? "Non Conformities" : "Reports"}
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
