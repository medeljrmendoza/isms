import { useEffect, useState } from "react";
import { pmsWorkPlanService } from "./pmsWorkPlanService";
import type { PmsWorkPlanDetail, PmsWorkPlanOption, PmsWorkPlanRow } from "./pmsWorkPlan";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { PmsWorkPlanForm } from "./PmsWorkPlanForm";
import { PmsWorkPlanViewModal } from "./PmsWorkPlanViewModal";

const PER_PAGE = 10;

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
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("desc");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<PmsWorkPlanDetail | null>(null);
  const [viewing, setViewing] = useState<PmsWorkPlanDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    pmsWorkPlanService.options().then((data) => setVessels(data.vessels)).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (!appliedVesselId) return;
    setLoading(true);
    pmsWorkPlanService
      .list({ vessel_id: Number(appliedVesselId), page, per_page: PER_PAGE, sort, direction })
      .then((data) => {
        setRows(data.rows);
        setLastPage(data.meta.last_page);
        setTotal(data.meta.total);
        setError(null);
      })
      .catch(() => setError("Couldn't load Unplanned Maintenance records. Please try again."))
      .finally(() => setLoading(false));
  }, [appliedVesselId, page, sort, direction, reloadKey]);

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

  const reload = () => setReloadKey((k) => k + 1);

  const openEdit = async (id: number) => {
    setActionError(null);
    try {
      const detail = await pmsWorkPlanService.show(id);
      setEditing(detail);
      setFormOpen(true);
    } catch {
      setActionError("Couldn't load this record. Please try again.");
    }
  };

  const openView = async (id: number) => {
    setActionError(null);
    try {
      setViewing(await pmsWorkPlanService.show(id));
    } catch {
      setActionError("Couldn't load this record. Please try again.");
    }
  };

  const handleDelete = async (row: PmsWorkPlanRow) => {
    if (!window.confirm("Are you sure you want to DELETE this item?")) return;
    setActionError(null);
    try {
      await pmsWorkPlanService.destroy(row.id);
      reload();
    } catch {
      setActionError("Action failed. Please try again.");
    }
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">PMS Unplanned Maintenance</h1>
          <Button
            type="button"
            variant="success"
            className="!px-3 !py-1.5 text-sm"
            onClick={() => {
              setEditing(null);
              setFormOpen(true);
            }}
          >
            + Add Item
          </Button>
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
                    <th className="px-2 py-1.5 font-semibold text-slate-600">ACTIONS</th>
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
                      <td className="whitespace-nowrap px-2 py-1.5">
                        <div className="flex gap-1">
                          <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => openEdit(row.id)}>
                            Edit
                          </Button>
                          <Button
                            type="button"
                            variant="secondary"
                            className="!px-1.5 !py-0.5 text-xs text-red-600"
                            onClick={() => handleDelete(row)}
                          >
                            Delete
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {rows.length === 0 && !loading && !error && (
                    <tr>
                      <td colSpan={COLUMNS.length + 1} className="px-2 py-6 text-center text-sm text-slate-400">
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

      {formOpen && (
        <Modal title={editing ? `Edit — ${editing.ticket_no}` : "Add Item"} onClose={() => setFormOpen(false)}>
          <PmsWorkPlanForm
            adhoc={editing ?? undefined}
            vessels={vessels}
            onCancel={() => setFormOpen(false)}
            onSuccess={() => {
              setFormOpen(false);
              reload();
            }}
          />
        </Modal>
      )}

      {viewing && <PmsWorkPlanViewModal adhoc={viewing} onClose={() => setViewing(null)} />}
    </div>
  );
}
