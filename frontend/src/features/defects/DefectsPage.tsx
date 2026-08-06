import { useEffect, useState } from "react";
import { defectsService } from "./defectsService";
import { PRIORITY_LABELS } from "./defects";
import type { DefectDetail, DefectOption, DefectRow } from "./defects";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { DefectForm } from "./DefectForm";
import { DefectViewModal } from "./DefectViewModal";

const PER_PAGE = 10;

const COLUMNS = [
  { key: "sl_no", label: "SL NO.", sortable: true },
  { key: "vessel", label: "VESSEL", sortable: false },
  { key: "defect_date", label: "DATE", sortable: true },
  { key: "priority", label: "PRIORITY", sortable: true },
  { key: "category", label: "CATEGORY", sortable: true },
  { key: "compl_code", label: "COMPL CODE", sortable: true },
  { key: "description", label: "DESCRIPTION", sortable: false },
  { key: "present_status", label: "PRESENT STATUS", sortable: false },
  { key: "expected_compl_date", label: "EXPECTED COMPL DATE", sortable: false },
  { key: "compl_date", label: "COMPL DATE", sortable: false },
];

/**
 * Ported from admin/defect_list/defect_list_v.php. Not ported: Print
 * (no report header/footer anywhere in this migration) and the
 * user_level-gated Edit button (every action is available here). This
 * module has no Delete action in the legacy UI — only Add/Edit/View —
 * so none is offered here either.
 */
export function DefectsPage() {
  const [vessels, setVessels] = useState<DefectOption[]>([]);

  const [vesselId, setVesselId] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [priority, setPriority] = useState("");

  const [applied, setApplied] = useState({ vesselId: "", dateFrom: "", dateTo: "", priority: "" });

  const [rows, setRows] = useState<DefectRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("desc");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filterError, setFilterError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<DefectDetail | null>(null);
  const [viewing, setViewing] = useState<DefectDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    defectsService.options().then((data) => setVessels(data.vessels)).catch(() => undefined);
  }, []);

  useEffect(() => {
    setLoading(true);
    defectsService
      .list({
        vessel_id: applied.vesselId || undefined,
        date_from: applied.dateFrom || undefined,
        date_to: applied.dateTo || undefined,
        priority: applied.priority || undefined,
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
      .catch(() => setError("Couldn't load Defect List records. Please try again."))
      .finally(() => setLoading(false));
  }, [applied, page, sort, direction, reloadKey]);

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
    if ((dateFrom || dateTo) && !(dateFrom && dateTo)) {
      setFilterError("Please fill in both Date From and Date To, or leave both blank.");
      return;
    }
    if (dateFrom && dateTo && dateFrom > dateTo) {
      setFilterError("Date From must not be after Date To.");
      return;
    }
    setFilterError(null);
    setApplied({ vesselId, dateFrom, dateTo, priority });
    setPage(1);
  };

  const resetFilter = () => {
    setVesselId("");
    setDateFrom("");
    setDateTo("");
    setPriority("");
    setFilterError(null);
    setApplied({ vesselId: "", dateFrom: "", dateTo: "", priority: "" });
    setPage(1);
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openEdit = async (id: number | string) => {
    setActionError(null);
    try {
      const detail = await defectsService.show(id);
      setEditing(detail);
      setFormOpen(true);
    } catch {
      setActionError("Couldn't load this record. Please try again.");
    }
  };

  const openView = async (id: number | string) => {
    setActionError(null);
    try {
      const detail = await defectsService.show(id);
      setViewing(detail);
    } catch {
      setActionError("Couldn't load this record. Please try again.");
    }
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">Defect List</h1>
          <Button
            type="button"
            variant="success"
            className="!px-3 !py-1.5 text-sm"
            onClick={() => {
              setEditing(null);
              setFormOpen(true);
            }}
          >
            + Add Defect
          </Button>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Vessel</label>
            <select value={vesselId} onChange={(e) => setVesselId(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">All</option>
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
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Priority</label>
            <select value={priority} onChange={(e) => setPriority(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">All</option>
              {Object.entries(PRIORITY_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </div>
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={applyFilter}>
            Filter
          </Button>
          <Button type="button" variant="info" className="!px-3 !py-1.5 text-sm" onClick={resetFilter}>
            View All
          </Button>
          {filterError && <p className="text-sm text-red-600">{filterError}</p>}
        </div>

        {actionError && <p className="px-4 pt-2 text-sm text-red-600">{actionError}</p>}

        <div className="overflow-x-auto px-4 py-3">
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
                  <td className="px-2 py-1.5">
                    <button type="button" className="text-sky-700 underline hover:text-sky-900" onClick={() => openView(row.id)}>
                      {row.sl_no}
                    </button>
                  </td>
                  <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.vessel}</td>
                  <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.defect_date}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.priority ?? "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.category ?? "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.compl_code}</td>
                  <td className="max-w-xs truncate px-2 py-1.5 text-slate-700" title={row.description}>
                    {row.description || "—"}
                  </td>
                  <td className="max-w-xs truncate px-2 py-1.5 text-slate-700" title={row.present_status ?? ""}>
                    {row.present_status || "—"}
                  </td>
                  <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.expected_compl_date ?? "—"}</td>
                  <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.compl_date ?? "—"}</td>
                  <td className="px-2 py-1.5">
                    {row.can_edit && (
                      <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => openEdit(row.id)}>
                        Edit
                      </Button>
                    )}
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
        </div>
      </div>

      {formOpen && (
        <Modal title={editing ? `Edit Defect — ${editing.sl_no}` : "Add Defect"} onClose={() => setFormOpen(false)}>
          <DefectForm
            defect={editing ?? undefined}
            vessels={vessels}
            onCancel={() => setFormOpen(false)}
            onSuccess={() => {
              setFormOpen(false);
              reload();
            }}
          />
        </Modal>
      )}

      {viewing && <DefectViewModal defect={viewing} onClose={() => setViewing(null)} />}
    </div>
  );
}
