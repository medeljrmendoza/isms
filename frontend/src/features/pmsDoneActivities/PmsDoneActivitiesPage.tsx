import { useEffect, useState } from "react";
import { pmsDoneActivitiesService } from "./pmsDoneActivitiesService";
import type { PmsDoneActivityOption, PmsDoneActivityRow } from "./pmsDoneActivities";
import { Button } from "../../components/ui/Button";

const PER_PAGE = 10;

const COLUMNS = [
  { key: "date_of_activity", label: "DATE OF ACTIVITY", sortable: true },
  { key: "previous_due_date", label: "DUE DATE", sortable: true },
  { key: "previous_last_done", label: "PREVIOUS DATE OF ACTIVITY", sortable: true },
  { key: "equipment_name", label: "COMPONENT", sortable: true },
  { key: "part_name", label: "PART", sortable: true },
  { key: "activity_code", label: "ACTIVITY CODE", sortable: false },
  { key: "activity_name", label: "ACTIVITY", sortable: true },
  { key: "frequency", label: "FREQUENCY", sortable: false },
  { key: "incharge", label: "IN-CHARGE", sortable: true },
  { key: "reported_by", label: "REPORTED BY", sortable: true },
  { key: "created_at", label: "DATE REPORTED", sortable: true },
];

/** Ported from admin/pms_done_activities/pms_done_activities_v.php. Read-only report — no add/edit/delete anywhere in the legacy view. */
export function PmsDoneActivitiesPage() {
  const [vessels, setVessels] = useState<PmsDoneActivityOption[]>([]);

  const [vesselId, setVesselId] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [filterError, setFilterError] = useState<string | null>(null);

  const [applied, setApplied] = useState<{ vesselId: string; dateFrom: string; dateTo: string } | null>(null);

  const [rows, setRows] = useState<PmsDoneActivityRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("desc");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    pmsDoneActivitiesService.options().then((data) => setVessels(data.vessels)).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (!applied) return;
    setLoading(true);
    pmsDoneActivitiesService
      .list({
        vessel_id: Number(applied.vesselId),
        date_from: applied.dateFrom,
        date_to: applied.dateTo,
        page,
        per_page: PER_PAGE,
        sort,
        direction,
      })
      .then((data) => {
        setRows(data.rows);
        setLastPage(data.meta.last_page);
        setTotal(data.meta.total);
        setError(null);
      })
      .catch(() => setError("Couldn't load Done Activities. Please try again."))
      .finally(() => setLoading(false));
  }, [applied, page, sort, direction]);

  const handleSort = (columnKey: string) => {
    if (sort === columnKey) {
      setDirection((prev) => (prev === "asc" ? "desc" : "asc"));
    } else {
      setSort(columnKey);
      setDirection("asc");
    }
    setPage(1);
  };

  const applyFilter = () => {
    if (!vesselId) {
      setFilterError("Please select Vessel.");
      return;
    }
    if (!dateFrom) {
      setFilterError("Please select Date From.");
      return;
    }
    if (!dateTo) {
      setFilterError("Please select Date To.");
      return;
    }
    setFilterError(null);
    setApplied({ vesselId, dateFrom, dateTo });
    setPage(1);
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">PMS Done Activities</h1>
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
              max={new Date().toISOString().slice(0, 10)}
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
          </div>
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={applyFilter}>
            Filter
          </Button>
          {filterError && <p className="text-sm text-red-600">{filterError}</p>}
        </div>

        <div className="overflow-x-auto px-4 py-3">
          {!applied ? (
            <p className="py-6 text-center text-sm text-slate-400">Select Vessel, Date From, and Date To, then click Filter.</p>
          ) : (
            <>
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-slate-200 bg-slate-50">
                    {COLUMNS.map((column) => (
                      <th
                        key={column.key}
                        onClick={column.sortable ? () => handleSort(column.key) : undefined}
                        className={`whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600 ${
                          column.sortable ? "cursor-pointer select-none hover:text-slate-900" : ""
                        }`}
                      >
                        {column.label}
                        {sort === column.key && (direction === "asc" ? " ▲" : " ▼")}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.id} className="border-b border-slate-100">
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.date_of_activity}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.previous_due_date ?? "—"}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.previous_last_done ?? "—"}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.equipment_name ?? "—"}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.part_name ?? "—"}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.activity_code ?? "—"}</td>
                      <td className="px-2 py-1.5 text-slate-700">{row.activity_name}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.frequency ?? "—"}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.incharge ?? "—"}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.reported_by ?? "—"}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.created_at}</td>
                    </tr>
                  ))}
                  {rows.length === 0 && !loading && !error && (
                    <tr>
                      <td colSpan={COLUMNS.length} className="px-2 py-6 text-center text-sm text-slate-400">
                        No records.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>

              {loading && (
                <div className="flex items-center gap-2 py-2 text-xs text-slate-400">
                  <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" />
                  Loading...
                </div>
              )}
              {error && <p className="text-xs text-red-600">{error}</p>}

              <div className="flex items-center justify-between pt-3 text-xs text-slate-500">
                <span>{total} total</span>
                <div className="flex items-center gap-2">
                  <Button type="button" variant="secondary" className="!px-2 !py-0.5 text-xs" disabled={page <= 1} onClick={() => setPage((prev) => prev - 1)}>
                    Prev
                  </Button>
                  <span>
                    Page {page} of {lastPage}
                  </span>
                  <Button
                    type="button"
                    variant="secondary"
                    className="!px-2 !py-0.5 text-xs"
                    disabled={page >= lastPage}
                    onClick={() => setPage((prev) => prev + 1)}
                  >
                    Next
                  </Button>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
