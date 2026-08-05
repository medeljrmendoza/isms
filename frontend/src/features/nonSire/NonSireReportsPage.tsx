import { useEffect, useState } from "react";
import { nonSireReportService } from "./nonSireReportService";
import type { NonSireReportDetail, NonSireReportOption, NonSireReportRow } from "./nonSire";
import { Button } from "../../components/ui/Button";
import { Modal } from "../../components/ui/Modal";
import { NonSireReportForm } from "./NonSireReportForm";
import { NonSireReportViewModal } from "./NonSireReportViewModal";

const PER_PAGE = 10;

const MODULE_COLUMNS = [
  { key: "vessel", label: "VESSEL", sortable: false },
  { key: "added_by", label: "ADDED BY", sortable: true },
  { key: "dateof_inspection", label: "DATE", sortable: true },
  { key: "placeof_inspection", label: "PLACE OF INSPECTION", sortable: true },
  { key: "company_name", label: "COMPANY", sortable: true },
  { key: "inspector_name", label: "INSPECTOR", sortable: true },
  { key: "inspection_type", label: "INSPECTION TYPE", sortable: true },
  { key: "pass_fail", label: "PASS/FAIL", sortable: true },
  { key: "published", label: "PUBLISHED", sortable: false },
  { key: "is_approved", label: "APPROVED", sortable: false },
];

function FlagIcon({ value }: { value: boolean | null }) {
  if (value === null) return <span className="text-slate-300">—</span>;
  return value ? <span className="text-green-600">✓</span> : <span className="text-red-500">✕</span>;
}

export function NonSireReportsPage() {
  const [vessels, setVessels] = useState<NonSireReportOption[]>([]);
  const [vesselId, setVesselId] = useState("ALL");
  const [appliedVesselId, setAppliedVesselId] = useState("ALL");

  const [rows, setRows] = useState<NonSireReportRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("desc");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<NonSireReportDetail | null>(null);
  const [viewing, setViewing] = useState<NonSireReportDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    nonSireReportService.options().then((data) => {
      setVessels(data.vessels);
    }).catch(() => undefined);
  }, []);

  useEffect(() => {
    const handle = window.setTimeout(() => {
      setSearch(searchInput.trim());
      setPage(1);
    }, 300);
    return () => window.clearTimeout(handle);
  }, [searchInput]);

  useEffect(() => {
    let isMounted = true;
    setLoading(true);

    nonSireReportService
      .list({
        page,
        per_page: PER_PAGE,
        search: search || undefined,
        sort,
        direction,
        vessel_id: appliedVesselId,
      })
      .then((data) => {
        if (!isMounted) return;
        setRows(data.rows);
        setLastPage(data.meta.last_page);
        setTotal(data.meta.total);
        setError(null);
      })
      .catch(() => {
        if (isMounted) setError("Couldn't load Non-SIRE reports. Please try again.");
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [page, search, sort, direction, appliedVesselId, reloadKey]);

  const handleSort = (columnKey: string) => {
    if (sort === columnKey) {
      setDirection((prev) => (prev === "asc" ? "desc" : "asc"));
    } else {
      setSort(columnKey);
      setDirection("asc");
    }
    setPage(1);
  };

  const applyFilters = () => {
    setAppliedVesselId(vesselId);
    setPage(1);
  };

  const resetFilters = () => {
    setVesselId("ALL");
    setAppliedVesselId("ALL");
    setPage(1);
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openView = async (id: number | string) => {
    setActionError(null);
    const detail = await nonSireReportService.show(id);
    setViewing(detail);
  };

  const openEdit = async (id: number | string) => {
    setActionError(null);
    const detail = await nonSireReportService.show(id);
    setEditing(detail);
    setFormOpen(true);
  };

  const runAction = async (action: () => Promise<unknown>) => {
    setActionError(null);
    try {
      await action();
      reload();
    } catch {
      setActionError("Action failed. Please try again.");
    }
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">Non-SIRE</h1>
          <Button
            type="button"
            variant="success"
            className="!px-3 !py-1.5 text-sm"
            onClick={() => {
              setEditing(null);
              setFormOpen(true);
            }}
          >
            + Add Report
          </Button>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Vessel</label>
            <select
              value={vesselId}
              onChange={(e) => setVesselId(e.target.value)}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="ALL">All</option>
              {vessels.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.label}
                </option>
              ))}
            </select>
          </div>
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={applyFilters}>
            Filter
          </Button>
          <Button type="button" variant="info" className="!px-3 !py-1.5 text-sm" onClick={resetFilters}>
            View All
          </Button>

          <div className="ml-auto flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Search</label>
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Search..."
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
          </div>
        </div>

        {actionError && <p className="px-4 pt-2 text-sm text-red-600">{actionError}</p>}

        <div className="overflow-x-auto px-4 py-3">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50">
                {MODULE_COLUMNS.map((column) => (
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
                    <button type="button" className="text-blue-600 hover:underline" onClick={() => openView(row.id)}>
                      {row.vessel}
                    </button>
                  </td>
                  <td className="px-2 py-1.5 text-slate-700">{row.added_by}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.dateof_inspection}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.placeof_inspection ?? "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.company_name ?? "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.inspector_name ?? "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.inspection_type ?? "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.pass_fail ?? "—"}</td>
                  <td className="px-2 py-1.5 text-center">
                    <FlagIcon value={row.published} />
                  </td>
                  <td className="px-2 py-1.5 text-center">
                    <FlagIcon value={row.is_approved} />
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="flex flex-wrap gap-1">
                      {row.can_edit && (
                        <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => openEdit(row.id)}>
                          Edit
                        </Button>
                      )}
                      {row.can_publish && (
                        <Button
                          type="button"
                          variant="secondary"
                          className="!px-1.5 !py-0.5 text-xs"
                          // row.id is always numeric here: can_publish is only true for local rows.
                          onClick={() => runAction(() => nonSireReportService.publish(row.id as number))}
                        >
                          {row.published ? "Unpublish" : "Publish"}
                        </Button>
                      )}
                      {row.can_approve && (
                        <Button
                          type="button"
                          variant="success"
                          className="!px-1.5 !py-0.5 text-xs"
                          // row.id is always numeric here: can_approve is only true for local rows.
                          onClick={() => runAction(() => nonSireReportService.approve(row.id as number))}
                        >
                          Approve
                        </Button>
                      )}
                      {row.can_delete && (
                        <Button
                          type="button"
                          variant="secondary"
                          className="!px-1.5 !py-0.5 text-xs text-red-600"
                          onClick={() => {
                            if (window.confirm(`Delete this report for ${row.vessel}?`)) {
                              // row.id is always numeric here: can_delete is only true for local rows.
                              runAction(() => nonSireReportService.destroy(row.id as number));
                            }
                          }}
                        >
                          Delete
                        </Button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && !loading && !error && (
                <tr>
                  <td colSpan={MODULE_COLUMNS.length + 1} className="px-2 py-6 text-center text-sm text-slate-400">
                    No items.
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
              <Button
                type="button"
                variant="secondary"
                className="!px-2 !py-0.5 text-xs"
                disabled={page <= 1}
                onClick={() => setPage((prev) => prev - 1)}
              >
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
        <Modal title={editing ? `Edit Report — ${editing.vessel}` : "Add Non-SIRE Report"} onClose={() => setFormOpen(false)}>
          <NonSireReportForm
            nonSireReport={editing ?? undefined}
            onCancel={() => setFormOpen(false)}
            onSuccess={() => {
              setFormOpen(false);
              reload();
            }}
          />
        </Modal>
      )}

      {viewing && <NonSireReportViewModal nonSireReport={viewing} onClose={() => setViewing(null)} />}
    </div>
  );
}
