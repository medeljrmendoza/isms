import { useEffect, useState } from "react";
import { pmsDepartmentService } from "./pmsDepartmentService";
import type { PmsDepartmentRow } from "./pmsDepartment";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { PmsDepartmentForm } from "./PmsDepartmentForm";

const PER_PAGE = 10;

/** Ported from admin/pms_setup_department/pms_setup_department_v.php. */
export function PmsDepartmentPage() {
  const [rows, setRows] = useState<PmsDepartmentRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("desc");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<PmsDepartmentRow | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    pmsDepartmentService
      .list({ page, per_page: PER_PAGE, search: search || undefined, sort, direction })
      .then((data) => {
        setRows(data.rows);
        setLastPage(data.meta.last_page);
        setTotal(data.meta.total);
        setError(null);
      })
      .catch(() => setError("Couldn't load departments. Please try again."))
      .finally(() => setLoading(false));
  }, [page, search, sort, direction, reloadKey]);

  const handleSort = () => {
    setDirection((prev) => (sort === "name" ? (prev === "asc" ? "desc" : "asc") : "asc"));
    setSort("name");
    setPage(1);
  };

  const reload = () => setReloadKey((k) => k + 1);

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
          <h1 className="text-base font-semibold text-slate-800">PMS Department</h1>
          <Button
            type="button"
            variant="success"
            className="!px-3 !py-1.5 text-sm"
            onClick={() => {
              setEditing(null);
              setFormOpen(true);
            }}
          >
            + Add Department
          </Button>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Search</label>
            <input
              type="text"
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
              placeholder="Department name..."
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
          </div>
        </div>

        {actionError && <p className="px-4 pt-2 text-sm text-red-600">{actionError}</p>}

        <div className="overflow-x-auto px-4 py-3">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50">
                <th
                  onClick={handleSort}
                  className="cursor-pointer select-none whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600 hover:text-slate-900"
                >
                  DEPARTMENT NAME
                  {sort === "name" && (direction === "asc" ? " ▲" : " ▼")}
                </th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">STATUS</th>
                <th className="px-2 py-1.5 font-semibold text-slate-600">ACTIONS</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-slate-100">
                  <td className="px-2 py-1.5 text-slate-700">{row.name}</td>
                  <td className="px-2 py-1.5">
                    <span className={row.is_active ? "font-semibold text-emerald-600" : "font-semibold text-red-500"}>
                      {row.is_active ? "Active" : "Inactive"}
                    </span>
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="flex flex-wrap gap-1">
                      <Button
                        type="button"
                        variant="secondary"
                        className="!px-1.5 !py-0.5 text-xs"
                        onClick={() => {
                          setEditing(row);
                          setFormOpen(true);
                        }}
                      >
                        Edit
                      </Button>
                      <Button
                        type="button"
                        variant={row.is_active ? "success" : "secondary"}
                        className="!px-1.5 !py-0.5 text-xs"
                        onClick={() => runAction(() => pmsDepartmentService.toggleStatus(row.id))}
                      >
                        {row.is_active ? "Inactivate" : "Activate"}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && !loading && !error && (
                <tr>
                  <td colSpan={3} className="px-2 py-6 text-center text-sm text-slate-400">
                    No departments.
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
              <Button type="button" variant="secondary" className="!px-2 !py-0.5 text-xs" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
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
                onClick={() => setPage((p) => p + 1)}
              >
                Next
              </Button>
            </div>
          </div>
        </div>
      </div>

      {formOpen && (
        <Modal title={editing ? `Edit Department — ${editing.name}` : "Add Department"} onClose={() => setFormOpen(false)}>
          <PmsDepartmentForm
            record={editing ?? undefined}
            onCancel={() => setFormOpen(false)}
            onSuccess={() => {
              setFormOpen(false);
              reload();
            }}
          />
        </Modal>
      )}
    </div>
  );
}
