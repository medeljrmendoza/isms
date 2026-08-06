import { useEffect, useState } from "react";
import { pmsWorkPlanService } from "./pmsWorkPlanService";
import type { PmsWorkPlanDetail, PmsWorkPlanOption, PmsWorkPlanRow } from "./pmsWorkPlan";
import { Button } from "../../components/ui/Button";
import { PmsWorkPlanViewModal } from "./PmsWorkPlanViewModal";

const COLUMNS = [
  { key: "ticket_no", label: "TICKET NO.", sortable: true },
  { key: "department", label: "DEPARTMENT", sortable: true },
  { key: "component", label: "COMPONENT", sortable: false },
  { key: "part", label: "PART", sortable: false },
  { key: "activity_name", label: "ACTIVITY", sortable: true },
  { key: "incharge", label: "IN-CHARGE", sortable: true },
  { key: "date_of_activity", label: "DATE OF ACTIVITY", sortable: true },
];

/** Ported from admin/pms_work_plan/work_plan_v.php. */
export function PmsWorkPlanPage() {
  const [vessels, setVessels] = useState<PmsWorkPlanOption[]>([]);
  const [vesselId, setVesselId] = useState("");
  const [appliedVesselId, setAppliedVesselId] = useState("");
  const [filterError, setFilterError] = useState<string | null>(null);

  const [rows, setRows] = useState<PmsWorkPlanRow[]>([]);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("desc");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [viewing, setViewing] = useState<PmsWorkPlanDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    pmsWorkPlanService.options().then((data) => setVessels(data.vessels)).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (!appliedVesselId) return;
    setLoading(true);
    pmsWorkPlanService
      .list({ vessel_id: appliedVesselId, page, per_page: perPage, search: search || undefined, sort, direction })
      .then((data) => {
        setRows(data.rows);
        setLastPage(data.meta.last_page);
        setTotal(data.meta.total);
        setError(null);
      })
      .catch(() => setError("Couldn't load Unplanned Maintenance records. Please try again."))
      .finally(() => setLoading(false));
  }, [appliedVesselId, page, perPage, search, sort, direction]);

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
    setFilterError(null);
    setAppliedVesselId(vesselId);
    setPage(1);
  };

  const openView = async (id: number | string) => {
    setActionError(null);
    try {
      setViewing(await pmsWorkPlanService.show(id));
    } catch {
      setActionError("Couldn't load this record. Please try again.");
    }
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">PMS Unplanned Maintenance</h1>
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
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={applyFilter}>
            Filter
          </Button>
          {filterError && <p className="text-sm text-red-600">{filterError}</p>}
        </div>

        {actionError && <p className="px-4 pt-2 text-sm text-red-600">{actionError}</p>}

        <div className="overflow-x-auto px-4 py-3">
          {!appliedVesselId ? (
            <p className="py-6 text-center text-sm text-slate-400">Select a vessel and click Filter.</p>
          ) : (
            <>
              <div className="flex flex-wrap items-center justify-between gap-3 pb-2">
                <label className="flex items-center gap-2 text-xs text-slate-500">
                  Show
                  <select
                    value={perPage}
                    onChange={(e) => {
                      setPerPage(Number(e.target.value));
                      setPage(1);
                    }}
                    className="rounded-md border border-slate-300 px-2 py-1 text-sm"
                  >
                    {[10, 25, 50, 100].map((n) => (
                      <option key={n} value={n}>
                        {n}
                      </option>
                    ))}
                  </select>
                  entries
                </label>
                <label className="flex items-center gap-2 text-xs text-slate-500">
                  Search:
                  <input
                    type="search"
                    value={search}
                    onChange={(e) => {
                      setSearch(e.target.value);
                      setPage(1);
                    }}
                    className="rounded-md border border-slate-300 px-2 py-1 text-sm"
                  />
                </label>
              </div>

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
                      <td className="whitespace-nowrap px-2 py-1.5">
                        <button type="button" className="text-sky-700 underline hover:text-sky-900" onClick={() => openView(row.id)}>
                          {row.ticket_no}
                        </button>
                      </td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.department ?? "—"}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.component ?? "—"}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.part ?? "—"}</td>
                      <td className="px-2 py-1.5 text-slate-700">{row.activity_name}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.incharge}</td>
                      <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.date_of_activity}</td>
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

      {viewing && <PmsWorkPlanViewModal adhoc={viewing} onClose={() => setViewing(null)} />}
    </div>
  );
}
