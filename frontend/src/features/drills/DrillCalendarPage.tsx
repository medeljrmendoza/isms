import { useEffect, useState } from "react";
import { drillService } from "./drillService";
import type { DrillGridRow, DrillOption, DrillReportDetail } from "./drill";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { DrillCellModal } from "./DrillCellModal";
import { DrillReportForm } from "./DrillReportForm";
import { DrillReportViewModal } from "./DrillReportViewModal";

const MONTHS = [
  { key: 1, label: "JAN" },
  { key: 2, label: "FEB" },
  { key: 3, label: "MAR" },
  { key: 4, label: "APR" },
  { key: 5, label: "MAY" },
  { key: 6, label: "JUN" },
  { key: 7, label: "JUL" },
  { key: 8, label: "AUG" },
  { key: 9, label: "SEP" },
  { key: 10, label: "OCT" },
  { key: 11, label: "NOV" },
  { key: 12, label: "DEC" },
];

function StatusDot({ status }: { status: DrillGridRow["status"] }) {
  if (status === "overdue") return <span className="inline-block h-3 w-3 rounded-full bg-red-600" title="Overdue" />;
  if (status === "upcoming") return <span className="inline-block h-3 w-3 rounded-full bg-sky-400" title="Upcoming in 30 Days" />;
  return null;
}

/** Ported from admin/drillreports/drill_calendar_v.php. */
export function DrillCalendarPage() {
  const [vessels, setVessels] = useState<DrillOption[]>([]);
  const [years, setYears] = useState<number[]>([]);
  const [vesselId, setVesselId] = useState<string>("");
  const [year, setYear] = useState<string>("");
  const [appliedVesselId, setAppliedVesselId] = useState<number | string | null>(null);
  const [appliedYear, setAppliedYear] = useState<number | null>(null);

  const [rows, setRows] = useState<DrillGridRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [cell, setCell] = useState<{ drillListId: number | string; drillName: string; month: number } | null>(null);
  const [viewing, setViewing] = useState<DrillReportDetail | null>(null);
  const [editing, setEditing] = useState<DrillReportDetail | null>(null);

  useEffect(() => {
    drillService.options().then((data) => {
      setVessels(data.vessels);
      setYears(data.years);
      if (data.vessels.length > 0) setVesselId(String(data.vessels[0].id));
      if (data.years.length > 0) setYear(String(data.years[0]));
    });
  }, []);

  useEffect(() => {
    if (appliedVesselId === null || appliedYear === null) return;

    setLoading(true);
    drillService
      .calendar(appliedVesselId, appliedYear)
      .then((data) => {
        setRows(data.rows);
        setError(null);
      })
      .catch(() => setError("Couldn't load the drill calendar. Please try again."))
      .finally(() => setLoading(false));
  }, [appliedVesselId, appliedYear, reloadKey]);

  const applyFilter = () => {
    if (!vesselId || !year) return;
    setAppliedVesselId(vesselId);
    setAppliedYear(Number(year));
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openView = async (reportId: number | string) => {
    const detail = await drillService.show(reportId);
    setCell(null);
    setViewing(detail);
  };

  const openEdit = async (reportId: number | string) => {
    const detail = await drillService.show(reportId);
    setCell(null);
    setEditing(detail);
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">Drill Reports</h1>
          <div className="flex items-center gap-3 text-xs text-slate-600">
            <span className="font-semibold">LEGEND:</span>
            <span className="flex items-center gap-1">
              <span className="inline-block h-3 w-3 rounded-full bg-red-600" /> Overdue
            </span>
            <span className="flex items-center gap-1">
              <span className="inline-block h-3 w-3 rounded-full bg-sky-400" /> Upcoming in 30 Days
            </span>
          </div>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Vessel</label>
            <select value={vesselId} onChange={(e) => setVesselId(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">Select vessel...</option>
              {vessels.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.label}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Year</label>
            <select value={year} onChange={(e) => setYear(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">Select year...</option>
              {years.map((y) => (
                <option key={y} value={y}>
                  {y}
                </option>
              ))}
            </select>
          </div>
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={applyFilter} disabled={!vesselId || !year}>
            Filter
          </Button>
        </div>

        <div className="overflow-x-auto px-4 py-3">
          {appliedVesselId === null && <p className="py-6 text-center text-sm text-slate-400">Select a vessel and year, then click Filter.</p>}

          {appliedVesselId !== null && (
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50">
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">TYPE</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">DRILL NAME</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">FREQUENCY</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">LAST DRILL</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">NEXT DRILL</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">STATUS</th>
                  {MONTHS.map((m) => (
                    <th key={m.key} className="whitespace-nowrap px-1 py-1.5 text-center font-semibold text-slate-600">
                      {m.label}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-slate-100">
                    <td className="px-2 py-1.5 text-slate-700">{row.drill_type ?? "—"}</td>
                    <td className="px-2 py-1.5 text-slate-700">{row.name}</td>
                    <td className="px-2 py-1.5 text-slate-700">{row.frequency}</td>
                    <td className="px-2 py-1.5 text-slate-700">{row.last_drill ?? "—"}</td>
                    <td className="px-2 py-1.5 text-slate-700">{row.next_drill ?? "—"}</td>
                    <td className="px-2 py-1.5 text-center">
                      <StatusDot status={row.status} />
                    </td>
                    {MONTHS.map((m) => {
                      const items = row.months[String(m.key)] ?? [];
                      return (
                        <td key={m.key} className="px-1 py-1.5 text-center">
                          {items.length === 0 ? (
                            <span className="text-slate-300">—</span>
                          ) : (
                            <button
                              type="button"
                              className="text-blue-600 hover:underline"
                              onClick={() => setCell({ drillListId: row.id, drillName: row.name, month: m.key })}
                            >
                              {items.map((i) => i.day).join(", ")}
                            </button>
                          )}
                        </td>
                      );
                    })}
                  </tr>
                ))}
                {rows.length === 0 && !loading && !error && (
                  <tr>
                    <td colSpan={6 + MONTHS.length} className="px-2 py-6 text-center text-sm text-slate-400">
                      No drills scheduled for this vessel.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          )}

          {loading && (
            <div className="flex items-center gap-2 py-2 text-xs text-slate-400">
              <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" />
              Loading...
            </div>
          )}
          {error && <p className="text-xs text-red-600">{error}</p>}
        </div>
      </div>

      {cell && appliedVesselId !== null && appliedYear !== null && (
        <DrillCellModal
          drillListId={cell.drillListId}
          drillName={cell.drillName}
          vesselId={appliedVesselId}
          year={appliedYear}
          month={cell.month}
          onClose={() => setCell(null)}
          onView={openView}
          onEdit={openEdit}
        />
      )}

      {viewing && <DrillReportViewModal drillReport={viewing} onClose={() => setViewing(null)} />}

      {editing && (
        <Modal title={`Edit Drill Report — ${editing.vessel}`} onClose={() => setEditing(null)}>
          <DrillReportForm
            drillReport={editing}
            onCancel={() => setEditing(null)}
            onSuccess={() => {
              setEditing(null);
              reload();
            }}
          />
        </Modal>
      )}
    </div>
  );
}
