import { useEffect, useState } from "react";
import { nonconformityService } from "../api/nonconformityService";
import type { NonconformityDetail, NonconformityOption, NonconformityRow } from "../types/nonconformity";
import { Button } from "../components/ui/Button";
import { Modal } from "../components/ui/Modal";
import { NonconformityForm } from "../components/nonconformities/NonconformityForm";
import { NonconformityViewModal } from "../components/nonconformities/NonconformityViewModal";

const PER_PAGE = 10;

const MODULE_COLUMNS = [
  { key: "ncr_no", label: "NCR NO.", sortable: true },
  { key: "date_of_nc", label: "DATE OF NC", sortable: true },
  { key: "added_by", label: "ADDED BY", sortable: true },
  { key: "source_of_nc", label: "SOURCE", sortable: true },
  { key: "reported_by", label: "REPORTER", sortable: false },
  { key: "vessel_company", label: "VESSEL/COMPANY", sortable: false },
  { key: "description", label: "DESCRIPTION", sortable: true },
  { key: "is_published", label: "PUBLISHED", sortable: false },
  { key: "is_approved", label: "APPROVED", sortable: false },
  { key: "status", label: "STATUS", sortable: false },
];

function FlagIcon({ value }: { value: boolean | null }) {
  if (value === null) return <span className="text-slate-300">—</span>;
  return value ? <span className="text-green-600">✓</span> : <span className="text-red-500">✕</span>;
}

export function NonconformitiesPage() {
  const [vessels, setVessels] = useState<NonconformityOption[]>([]);
  const [vesselCompany, setVesselCompany] = useState("ALL");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [appliedFilters, setAppliedFilters] = useState({ vesselCompany: "ALL", dateFrom: "", dateTo: "" });

  const [rows, setRows] = useState<NonconformityRow[]>([]);
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
  const [editing, setEditing] = useState<NonconformityDetail | null>(null);
  const [viewing, setViewing] = useState<NonconformityDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    nonconformityService.options().then((data) => setVessels(data.vessels)).catch(() => undefined);
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

    nonconformityService
      .list({
        page,
        per_page: PER_PAGE,
        search: search || undefined,
        sort,
        direction,
        vessel_company: appliedFilters.vesselCompany,
        date_from: appliedFilters.dateFrom || undefined,
        date_to: appliedFilters.dateTo || undefined,
      })
      .then((data) => {
        if (!isMounted) return;
        setRows(data.rows);
        setLastPage(data.meta.last_page);
        setTotal(data.meta.total);
        setError(null);
      })
      .catch(() => {
        if (isMounted) setError("Couldn't load Nonconformities. Please try again.");
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
    setAppliedFilters({ vesselCompany, dateFrom, dateTo });
    setPage(1);
  };

  const resetFilters = () => {
    setVesselCompany("ALL");
    setDateFrom("");
    setDateTo("");
    setAppliedFilters({ vesselCompany: "ALL", dateFrom: "", dateTo: "" });
    setPage(1);
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openView = async (id: number) => {
    setActionError(null);
    const detail = await nonconformityService.show(id);
    setViewing(detail);
  };

  const openEdit = async (id: number) => {
    setActionError(null);
    const detail = await nonconformityService.show(id);
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
          <h1 className="text-base font-semibold text-slate-800">Nonconformities</h1>
          <Button
            type="button"
            variant="success"
            className="!px-3 !py-1.5 text-sm"
            onClick={() => {
              setEditing(null);
              setFormOpen(true);
            }}
          >
            + Add Non Conformity
          </Button>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Vessel / Company</label>
            <select
              value={vesselCompany}
              onChange={(e) => setVesselCompany(e.target.value)}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="ALL">All</option>
              <option value="COMPANY">Company</option>
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
                      {row.ncr_no}
                    </button>
                  </td>
                  <td className="px-2 py-1.5 text-slate-700">{row.date_of_nc}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.added_by}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.source_of_nc}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.reported_by}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.vessel_company}</td>
                  <td className="max-w-xs truncate px-2 py-1.5 text-slate-700" title={row.description}>
                    {row.description}
                  </td>
                  <td className="px-2 py-1.5 text-center">
                    <FlagIcon value={row.is_published} />
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
                          onClick={() => runAction(() => nonconformityService.publish(row.id))}
                        >
                          {row.is_published ? "Unpublish" : "Publish"}
                        </Button>
                      )}
                      {row.can_approve && (
                        <Button
                          type="button"
                          variant="success"
                          className="!px-1.5 !py-0.5 text-xs"
                          onClick={() => runAction(() => nonconformityService.approve(row.id))}
                        >
                          Approve
                        </Button>
                      )}
                      {row.can_reopen && (
                        <Button
                          type="button"
                          variant="secondary"
                          className="!px-1.5 !py-0.5 text-xs"
                          onClick={() => runAction(() => nonconformityService.reopen(row.id))}
                        >
                          Re-open
                        </Button>
                      )}
                      <Button
                        type="button"
                        variant="secondary"
                        className="!px-1.5 !py-0.5 text-xs text-red-600"
                        onClick={() => {
                          if (window.confirm(`Delete ${row.ncr_no}?`)) {
                            runAction(() => nonconformityService.destroy(row.id));
                          }
                        }}
                      >
                        Delete
                      </Button>
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
        <Modal title={editing ? `Edit ${editing.ncr_no}` : "Add Non Conformity"} onClose={() => setFormOpen(false)}>
          <NonconformityForm
            nonconformity={editing ?? undefined}
            onCancel={() => setFormOpen(false)}
            onSuccess={() => {
              setFormOpen(false);
              reload();
            }}
          />
        </Modal>
      )}

      {viewing && <NonconformityViewModal nonconformity={viewing} onClose={() => setViewing(null)} />}
    </div>
  );
}
