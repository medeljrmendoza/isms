import { useEffect, useState } from "react";
import { incidentReportService } from "./incidentReportService";
import type { IncidentReportDetail, IncidentReportOption, IncidentReportRow } from "./incidentReport";
import { Button } from "../../components/ui/Button";
import { Modal } from "../../components/ui/Modal";
import { IncidentReportForm } from "./IncidentReportForm";
import { IncidentReportViewModal } from "./IncidentReportViewModal";

const PER_PAGE = 10;

const MODULE_COLUMNS = [
  { key: "vessel", label: "VESSEL", sortable: false },
  { key: "dateof_report", label: "DATE OF REPORT", sortable: true },
  { key: "report_no", label: "REPORT NO.", sortable: true },
  { key: "nature", label: "NATURE", sortable: true },
  { key: "type", label: "TYPE", sortable: false },
  { key: "added_by", label: "ADDED BY", sortable: true },
  { key: "published", label: "PUBLISHED", sortable: false },
  { key: "is_approved", label: "APPROVED", sortable: false },
  { key: "status", label: "STATUS", sortable: false },
];

function FlagIcon({ value }: { value: boolean | null }) {
  if (value === null) return <span className="text-slate-300">—</span>;
  return value ? <span className="text-green-600">✓</span> : <span className="text-red-500">✕</span>;
}

export function IncidentReportsPage() {
  const [vessels, setVessels] = useState<IncidentReportOption[]>([]);
  const [years, setYears] = useState<string[]>([]);
  const [vesselId, setVesselId] = useState("ALL");
  const [year, setYear] = useState("");
  const [appliedFilters, setAppliedFilters] = useState({ vesselId: "ALL", year: "" });

  const [rows, setRows] = useState<IncidentReportRow[]>([]);
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
  const [editing, setEditing] = useState<IncidentReportDetail | null>(null);
  const [viewing, setViewing] = useState<IncidentReportDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    incidentReportService.options().then((data) => {
      setVessels(data.vessels);
      setYears(data.years);
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

    incidentReportService
      .list({
        page,
        per_page: PER_PAGE,
        search: search || undefined,
        sort,
        direction,
        vessel_id: appliedFilters.vesselId,
        year: appliedFilters.year || undefined,
      })
      .then((data) => {
        if (!isMounted) return;
        setRows(data.rows);
        setLastPage(data.meta.last_page);
        setTotal(data.meta.total);
        setError(null);
      })
      .catch(() => {
        if (isMounted) setError("Couldn't load Incident Reports. Please try again.");
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [page, search, sort, direction, appliedFilters, reloadKey]);

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
    setAppliedFilters({ vesselId, year });
    setPage(1);
  };

  const resetFilters = () => {
    setVesselId("ALL");
    setYear("");
    setAppliedFilters({ vesselId: "ALL", year: "" });
    setPage(1);
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openView = async (id: number | string) => {
    setActionError(null);
    const detail = await incidentReportService.show(id);
    setViewing(detail);
  };

  const openEdit = async (id: number | string) => {
    setActionError(null);
    const detail = await incidentReportService.show(id);
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
          <h1 className="text-base font-semibold text-slate-800">Incident Report / HOR</h1>
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
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Year</label>
            <select
              value={year}
              onChange={(e) => setYear(e.target.value)}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">All</option>
              {years.map((y) => (
                <option key={y} value={y}>
                  {y}
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
                  <td className="px-2 py-1.5 text-slate-700">{row.dateof_report}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.report_no}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.nature}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.type}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.added_by}</td>
                  <td className="px-2 py-1.5 text-center">
                    <FlagIcon value={row.published} />
                  </td>
                  <td className="px-2 py-1.5 text-center">
                    <FlagIcon value={row.is_approved} />
                  </td>
                  <td className="px-2 py-1.5">
                    <span
                      className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                        row.status_color === "green" ? "bg-green-100 text-green-700" : "bg-amber-100 text-amber-700"
                      }`}
                    >
                      {row.status}
                    </span>
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
                          onClick={() => runAction(() => incidentReportService.publish(row.id as number))}
                        >
                          {row.published ? "Unpublish" : "Publish"}
                        </Button>
                      )}
                      {row.can_approve && (
                        <Button
                          type="button"
                          variant="success"
                          className="!px-1.5 !py-0.5 text-xs"
                          onClick={() => runAction(() => incidentReportService.approve(row.id as number))}
                        >
                          Approve
                        </Button>
                      )}
                      {row.can_reopen && (
                        <Button
                          type="button"
                          variant="secondary"
                          className="!px-1.5 !py-0.5 text-xs"
                          onClick={() => runAction(() => incidentReportService.reopen(row.id as number))}
                        >
                          Re-open
                        </Button>
                      )}
                      {row.can_delete && (
                        <Button
                          type="button"
                          variant="secondary"
                          className="!px-1.5 !py-0.5 text-xs text-red-600"
                          onClick={() => {
                            if (window.confirm(`Delete this report for ${row.vessel}?`)) {
                              runAction(() => incidentReportService.destroy(row.id as number));
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
        <Modal title={editing ? `Edit Report — ${editing.vessel}` : "Add Incident Report"} onClose={() => setFormOpen(false)}>
          <IncidentReportForm
            incidentReport={editing ?? undefined}
            onCancel={() => setFormOpen(false)}
            onSuccess={() => {
              setFormOpen(false);
              reload();
            }}
          />
        </Modal>
      )}

      {viewing && <IncidentReportViewModal incidentReport={viewing} onClose={() => setViewing(null)} />}
    </div>
  );
}
