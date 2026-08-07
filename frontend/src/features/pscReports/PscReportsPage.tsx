import { useEffect, useState } from "react";
import { pscReportService } from "./pscReportService";
import type { PscReportDetail, PscReportOption, PscReportRow } from "./pscReport";
import { Button } from "../../components/ui/Button";
import { Modal } from "../../components/ui/Modal";
import { PscReportForm } from "./PscReportForm";
import { PscReportViewModal } from "./PscReportViewModal";

const PER_PAGE = 10;

/** Matches psc_report_v.php's 9-column DataTables layout. */
const MODULE_COLUMNS = [
  { key: "ref_no", label: "REF. NO.", sortable: true },
  { key: "vessel", label: "VESSEL", sortable: false },
  { key: "dateof_inspection", label: "DATE OF INSPECTION", sortable: true },
  { key: "placeof_inspection", label: "PORT OF INSPECTION", sortable: false },
  { key: "name_psco", label: "NAME OF INSPECTOR", sortable: false },
  { key: "mou", label: "PSC MOU", sortable: false },
  { key: "nc", label: "NC", sortable: false },
  { key: "obs", label: "OBS", sortable: false },
  { key: "actions", label: "ACTIONS", sortable: false },
];

/** No Reopen: legacy's own Reopen button is dead code — see PscReportRepository. */
export function PscReportsPage() {
  const [vessels, setVessels] = useState<PscReportOption[]>([]);
  const [vesselId, setVesselId] = useState("ALL");
  const [appliedVesselId, setAppliedVesselId] = useState("ALL");

  const [rows, setRows] = useState<PscReportRow[]>([]);
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
  const [editing, setEditing] = useState<PscReportDetail | null>(null);
  const [viewing, setViewing] = useState<PscReportDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    pscReportService.options().then((data) => {
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

    pscReportService
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
        if (isMounted) setError("Couldn't load PSC Inspections. Please try again.");
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

  const openView = async (id: string) => {
    setActionError(null);
    const detail = await pscReportService.show(id);
    setViewing(detail);
  };

  const openAdd = () => {
    setEditing(null);
    setFormOpen(true);
  };

  const openEdit = async (id: string) => {
    setActionError(null);
    const detail = await pscReportService.show(id);
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
          <h1 className="text-base font-semibold text-slate-800">PSC Inspections</h1>
          <Button type="button" variant="success" className="!px-3 !py-1.5 text-sm" onClick={openAdd}>
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
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-slate-100">
                  <td className="px-2 py-1.5">
                    <button type="button" className="text-blue-600 hover:underline" onClick={() => openView(row.id)}>
                      {row.ref_no}
                    </button>
                  </td>
                  <td className="px-2 py-1.5 text-slate-700">{row.vessel}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.dateof_inspection}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.placeof_inspection ?? "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.name_psco ?? "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.mou ?? "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">
                    {row.total_nc_count > 0 ? `${row.pending_nc_count} / ${row.total_nc_count}` : "—"}
                  </td>
                  <td className="px-2 py-1.5 text-slate-700">
                    {row.total_obs_count > 0 ? `${row.pending_obs_count} / ${row.total_obs_count}` : "—"}
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="flex flex-wrap gap-1">
                      {row.can_edit && (
                        <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => openEdit(row.id)}>
                          Edit
                        </Button>
                      )}
                      {row.can_delete && (
                        <Button
                          type="button"
                          variant="secondary"
                          className="!px-1.5 !py-0.5 text-xs text-red-600"
                          onClick={() => {
                            if (window.confirm("Are you sure you want to DELETE this report?")) {
                              runAction(() => pscReportService.destroy(row.id));
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
                  <td colSpan={MODULE_COLUMNS.length} className="px-2 py-6 text-center text-sm text-slate-400">
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
        <Modal title={editing ? `Edit Report — ${editing.ref_no}` : "Add PSC Inspection Report"} onClose={() => setFormOpen(false)}>
          <PscReportForm
            pscReport={editing ?? undefined}
            onCancel={() => setFormOpen(false)}
            onSuccess={() => {
              setFormOpen(false);
              reload();
            }}
          />
        </Modal>
      )}

      {viewing && <PscReportViewModal pscReport={viewing} onClose={() => setViewing(null)} />}
    </div>
  );
}
