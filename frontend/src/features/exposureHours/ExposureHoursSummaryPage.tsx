import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { exposureHoursService } from "./exposureHoursService";
import type { ExposureHoursOption, ExposureHoursSummaryResponse } from "./exposureHours";
import { Button } from "../../components/ui/Button";
import { ExposureHoursLegendsModal } from "./ExposureHoursLegendsModal";

/** Ported from admin/exposurehours/summary_v.php. */
export function ExposureHoursSummaryPage() {
  const navigate = useNavigate();
  const [vessels, setVessels] = useState<ExposureHoursOption[]>([]);
  const [vesselId, setVesselId] = useState("ALL");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [appliedVesselId, setAppliedVesselId] = useState("ALL");
  const [appliedDateFrom, setAppliedDateFrom] = useState("");
  const [appliedDateTo, setAppliedDateTo] = useState("");

  const [data, setData] = useState<ExposureHoursSummaryResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showLegends, setShowLegends] = useState(false);

  useEffect(() => {
    exposureHoursService.options().then((opts) => setVessels(opts.vessels));
  }, []);

  useEffect(() => {
    setLoading(true);
    exposureHoursService
      .summary(appliedVesselId, { date_from: appliedDateFrom || undefined, date_to: appliedDateTo || undefined })
      .then((res) => {
        setData(res);
        setError(null);
      })
      .catch(() => setError("Couldn't load the Exposure Hours summary. Please try again."))
      .finally(() => setLoading(false));
  }, [appliedVesselId, appliedDateFrom, appliedDateTo]);

  const applyFilters = () => {
    setAppliedVesselId(vesselId);
    setAppliedDateFrom(dateFrom);
    setAppliedDateTo(dateTo);
  };

  const resetFilters = () => {
    setVesselId("ALL");
    setDateFrom("");
    setDateTo("");
    setAppliedVesselId("ALL");
    setAppliedDateFrom("");
    setAppliedDateTo("");
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">Exposure Hours — Summary</h1>
          <Button type="button" variant="secondary" className="!px-3 !py-1.5 text-sm" onClick={() => setShowLegends(true)}>
            Legends
          </Button>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Vessel</label>
            <select value={vesselId} onChange={(e) => setVesselId(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="ALL">All</option>
              {vessels.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.label}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Date From</label>
            <input
              type="date"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Date To</label>
            <input
              type="date"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
          </div>
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={applyFilters}>
            Filter
          </Button>
          <Button type="button" variant="info" className="!px-3 !py-1.5 text-sm" onClick={resetFilters}>
            View All
          </Button>
        </div>

        <div className="overflow-x-auto px-4 py-3">
          {loading && (
            <div className="flex items-center gap-2 py-2 text-xs text-slate-400">
              <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" />
              Loading...
            </div>
          )}
          {error && <p className="text-xs text-red-600">{error}</p>}

          {!loading && !error && data && (
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50">
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">VESSEL</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">FAT</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">PTD</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">PPD</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">LWC</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">RWC</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">MTC</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">TOTAL HOURS</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">LTI</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">TRC</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">LTIF</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">TRCF</th>
                </tr>
              </thead>
              <tbody>
                {data.rows.map((row) => (
                  <tr key={row.vessel_id} className="border-b border-slate-100">
                    <td className="px-2 py-1.5">
                      <button
                        type="button"
                        className="text-blue-600 hover:underline"
                        onClick={() => navigate(`/exposure_hours/${row.vessel_id}`)}
                      >
                        {row.vessel}
                      </button>
                    </td>
                    <td className="px-2 py-1.5 text-center text-slate-700">{row.no_of_fat}</td>
                    <td className="px-2 py-1.5 text-center text-slate-700">{row.no_of_ptd}</td>
                    <td className="px-2 py-1.5 text-center text-slate-700">{row.no_of_ppd}</td>
                    <td className="px-2 py-1.5 text-center text-slate-700">{row.no_of_lwc}</td>
                    <td className="px-2 py-1.5 text-center text-slate-700">{row.no_of_rwc}</td>
                    <td className="px-2 py-1.5 text-center text-slate-700">{row.no_of_mtc}</td>
                    <td className="px-2 py-1.5 text-center text-slate-700">{row.total_hours}</td>
                    <td className="px-2 py-1.5 text-center text-slate-700">{row.lti}</td>
                    <td className="px-2 py-1.5 text-center text-slate-700">{row.trc}</td>
                    <td className="px-2 py-1.5 text-center text-slate-700">{row.ltif}</td>
                    <td className="px-2 py-1.5 text-center text-slate-700">{row.trcf}</td>
                  </tr>
                ))}
                {data.rows.length === 0 && (
                  <tr>
                    <td colSpan={12} className="px-2 py-6 text-center text-sm text-slate-400">
                      No vessels.
                    </td>
                  </tr>
                )}
              </tbody>
              {data.rows.length > 0 && (
                <tfoot>
                  <tr className="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                    <td className="px-2 py-1.5 text-slate-800">TOTAL</td>
                    <td className="px-2 py-1.5 text-center text-slate-800">{data.totals.fat}</td>
                    <td className="px-2 py-1.5 text-center text-slate-800">{data.totals.ptd}</td>
                    <td className="px-2 py-1.5 text-center text-slate-800">{data.totals.ppd}</td>
                    <td className="px-2 py-1.5 text-center text-slate-800">{data.totals.lwc}</td>
                    <td className="px-2 py-1.5 text-center text-slate-800">{data.totals.rwc}</td>
                    <td className="px-2 py-1.5 text-center text-slate-800">{data.totals.mtc}</td>
                    <td className="px-2 py-1.5 text-center text-slate-800">{data.totals.total_hours}</td>
                    <td className="px-2 py-1.5 text-center text-slate-800">{data.totals.lti}</td>
                    <td className="px-2 py-1.5 text-center text-slate-800">{data.totals.trc}</td>
                    <td className="px-2 py-1.5 text-center text-slate-800">{data.totals.ltif}</td>
                    <td className="px-2 py-1.5 text-center text-slate-800">{data.totals.trcf}</td>
                  </tr>
                </tfoot>
              )}
            </table>
          )}
        </div>
      </div>

      {showLegends && <ExposureHoursLegendsModal onClose={() => setShowLegends(false)} />}
    </div>
  );
}
