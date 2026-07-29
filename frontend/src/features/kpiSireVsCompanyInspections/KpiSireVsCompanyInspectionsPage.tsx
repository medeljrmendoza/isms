import { useEffect, useState } from "react";
import { kpiSireService } from "../kpiSire/kpiSireService";
import { kpiCompanyInspectionsService } from "../kpiCompanyInspections/kpiCompanyInspectionsService";
import type { KpiOption } from "../kpi/kpi";
import { KpiGroupedBarChart } from "../kpi/KpiGroupedBarChart";
import { KpiDrillDownModal } from "../kpi/KpiDrillDownModal";
import { Button } from "../../components/ui/Button";

interface DrillDown {
  title: string;
  fetcher: Parameters<typeof KpiDrillDownModal>[0]["fetcher"];
}

/**
 * Ported from admin/kpi_sire_vs_company_inspections/kpi_company_inspections_v.php
 * — with an explicit scope change. Legacy's own two chart modes there
 * ("Observations per Chapter" and "Observations per Vessel") are both
 * entirely built on tb_observations, comparing SIRE vs Company
 * Inspection observation counts — no Observations module exists
 * anywhere in this migration, so neither mode has anything to port.
 * Reinterpreted (per explicit sign-off) as a comparison of SIRE vs
 * Company Inspection *report* counts per vessel instead, composed
 * entirely from the two KPI modules already built — no new backend.
 */
export function KpiSireVsCompanyInspectionsPage() {
  const [vessels, setVessels] = useState<KpiOption[]>([]);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [appliedFrom, setAppliedFrom] = useState<string | undefined>(undefined);
  const [appliedTo, setAppliedTo] = useState<string | undefined>(undefined);

  const [sireCounts, setSireCounts] = useState<number[]>([]);
  const [companyCounts, setCompanyCounts] = useState<number[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [dateError, setDateError] = useState<string | null>(null);

  const [drillDown, setDrillDown] = useState<DrillDown | null>(null);

  useEffect(() => {
    kpiSireService.options().then((data) => setVessels(data.vessels));
  }, []);

  useEffect(() => {
    if (vessels.length === 0) return;

    setLoading(true);
    Promise.all([
      kpiSireService.summary({ from: appliedFrom, to: appliedTo }),
      kpiCompanyInspectionsService.summary("vessel", { from: appliedFrom, to: appliedTo }),
    ])
      .then(([sireRows, companyRows]) => {
        const sireByLabel = new Map(sireRows.map((r) => [r.label, r.count]));
        const companyByLabel = new Map(companyRows.map((r) => [r.label, r.count]));

        setSireCounts(vessels.map((v) => sireByLabel.get(v.label) ?? 0));
        setCompanyCounts(vessels.map((v) => companyByLabel.get(v.label) ?? 0));
        setError(null);
      })
      .catch(() => setError("Couldn't load KPI data. Please try again."))
      .finally(() => setLoading(false));
  }, [vessels, appliedFrom, appliedTo]);

  const applyFilter = () => {
    if (from && to && from > to) {
      setDateError("Invalid date range: Date From must not be after Date To.");
      return;
    }
    setDateError(null);
    setAppliedFrom(from || undefined);
    setAppliedTo(to || undefined);
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">KPI — SIRE vs Company Inspections</h1>
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
            <KpiGroupedBarChart
              title={`SIRE vs Company Inspections ${appliedFrom || appliedTo ? `(${appliedFrom ?? "…"} – ${appliedTo ?? "…"})` : `- ${new Date().getFullYear()}`}`}
              yAxisLabel="Reports"
              categories={vessels.map((v) => v.label)}
              series={[
                {
                  name: "SIRE Reports",
                  colorClass: "bg-sky-500",
                  hoverColorClass: "group-hover:bg-sky-600",
                  values: sireCounts,
                  onBarClick: (index) => {
                    const vessel = vessels[index];
                    if (!vessel) return;
                    setDrillDown({
                      title: `SIRE Reports — ${vessel.label}`,
                      fetcher: (params) => kpiSireService.reportsByVessel(vessel.id, params),
                    });
                  },
                },
                {
                  name: "Company Inspection Reports",
                  colorClass: "bg-amber-500",
                  hoverColorClass: "group-hover:bg-amber-600",
                  values: companyCounts,
                  onBarClick: (index) => {
                    const vessel = vessels[index];
                    if (!vessel) return;
                    setDrillDown({
                      title: `Company Inspection Reports — ${vessel.label}`,
                      fetcher: (params) => kpiCompanyInspectionsService.reportsByVessel(vessel.id, params),
                    });
                  },
                },
              ]}
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
