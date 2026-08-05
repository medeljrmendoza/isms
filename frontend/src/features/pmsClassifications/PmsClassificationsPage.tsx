import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { pmsClassificationsService } from "./pmsClassificationsService";
import type { PmsClassificationDetail, PmsClassificationOption, PmsClassificationRow } from "./pmsClassifications";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { PmsClassificationForm } from "./PmsClassificationForm";

const PER_PAGE = 10;

/** Ported from admin/pms_setup_classification/pms_setup_classification_v.php. */
export function PmsClassificationsPage() {
  const navigate = useNavigate();

  const [departments, setDepartments] = useState<PmsClassificationOption[]>([]);
  const [vesselTypes, setVesselTypes] = useState<PmsClassificationOption[]>([]);
  const [departmentId, setDepartmentId] = useState("");
  const [vesselTypeId, setVesselTypeId] = useState("");

  const [rows, setRows] = useState<PmsClassificationRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<PmsClassificationDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [viewList, setViewList] = useState<{ title: string; items: string[] } | null>(null);

  useEffect(() => {
    pmsClassificationsService
      .options()
      .then((data) => {
        setDepartments(data.departments);
        setVesselTypes(data.vessel_types);
      })
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    setLoading(true);
    pmsClassificationsService
      .list({
        department_id: departmentId || undefined,
        vessel_type_id: vesselTypeId || undefined,
        page,
        per_page: PER_PAGE,
      })
      .then((data) => {
        setRows(data.rows);
        setLastPage(data.meta.last_page);
        setTotal(data.meta.total);
        setError(null);
      })
      .catch(() => setError("Couldn't load classifications. Please try again."))
      .finally(() => setLoading(false));
  }, [departmentId, vesselTypeId, page, reloadKey]);

  const reload = () => setReloadKey((k) => k + 1);

  const openAdd = () => {
    setEditing(null);
    setFormOpen(true);
  };

  const openEdit = async (id: number) => {
    setActionError(null);
    const detail = await pmsClassificationsService.show(id);
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
          <h1 className="text-base font-semibold text-slate-800">Classifications</h1>
          <Button type="button" variant="success" className="!px-3 !py-1.5 text-sm" onClick={openAdd}>
            + Add Item
          </Button>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Department</label>
            <select
              value={departmentId}
              onChange={(e) => {
                setDepartmentId(e.target.value);
                setPage(1);
              }}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">All departments</option>
              {departments.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.label}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Vessel Type</label>
            <select
              value={vesselTypeId}
              onChange={(e) => {
                setVesselTypeId(e.target.value);
                setPage(1);
              }}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">All vessel types</option>
              {vesselTypes.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        {actionError && <p className="px-4 pt-2 text-sm text-red-600">{actionError}</p>}

        <div className="overflow-x-auto px-4 py-3">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50">
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">CLASSIFICATION NAME</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">DESCRIPTION</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">SUB-CLASSIFICATIONS</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">DEPARTMENTS</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">VESSEL TYPES</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">STATUS</th>
                <th className="px-2 py-1.5 font-semibold text-slate-600">ACTIONS</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-slate-100">
                  <td className="px-2 py-1.5 font-medium text-slate-800">{row.name}</td>
                  <td className="max-w-xs px-2 py-1.5 text-slate-700">{row.description ?? "—"}</td>
                  <td className="px-2 py-1.5 text-center">
                    <Button
                      type="button"
                      variant={row.sub_classification_count > 0 ? "primary" : "success"}
                      className="!px-2 !py-0.5 text-xs"
                      onClick={() => navigate(`/pms_setup_classification/sub_classification/${row.id}`)}
                    >
                      {row.sub_classification_count > 0 ? "Sub-Classifications" : "+ Add"}
                    </Button>
                  </td>
                  <td className="px-2 py-1.5 text-center">
                    {row.department_count > 0 && row.departments ? (
                      <Button
                        type="button"
                        variant="info"
                        className="!px-2 !py-0.5 text-xs"
                        onClick={() => setViewList({ title: row.name, items: row.departments ?? [] })}
                      >
                        View ({row.department_count})
                      </Button>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className="px-2 py-1.5 text-center">
                    {row.vessel_type_count > 0 && row.vessel_types ? (
                      <Button
                        type="button"
                        variant="info"
                        className="!px-2 !py-0.5 text-xs"
                        onClick={() => setViewList({ title: row.name, items: row.vessel_types ?? [] })}
                      >
                        View ({row.vessel_type_count})
                      </Button>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className="px-2 py-1.5">
                    <span className={row.is_active ? "font-semibold text-emerald-600" : "font-semibold text-red-500"}>
                      {row.is_active ? "Active" : "Inactive"}
                    </span>
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="flex flex-wrap gap-1">
                      <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => openEdit(row.id)}>
                        Edit
                      </Button>
                      <Button
                        type="button"
                        variant={row.is_active ? "success" : "secondary"}
                        className="!px-1.5 !py-0.5 text-xs"
                        onClick={() => runAction(() => pmsClassificationsService.toggleStatus(row.id))}
                      >
                        {row.is_active ? "Inactivate" : "Activate"}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && !loading && !error && (
                <tr>
                  <td colSpan={7} className="px-2 py-6 text-center text-sm text-slate-400">
                    No classifications.
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
        <Modal title={editing ? `Edit Classification — ${editing.name}` : "Add Classification"} onClose={() => setFormOpen(false)}>
          <PmsClassificationForm
            record={editing ?? undefined}
            departments={departments}
            vesselTypes={vesselTypes}
            onCancel={() => setFormOpen(false)}
            onSuccess={() => {
              setFormOpen(false);
              reload();
            }}
          />
        </Modal>
      )}

      {viewList && (
        <Modal title={viewList.title} onClose={() => setViewList(null)}>
          <table className="w-full text-left text-sm">
            <tbody>
              {viewList.items.map((item) => (
                <tr key={item} className="border-b border-slate-100">
                  <td className="px-2 py-1.5 text-slate-700">{item}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Modal>
      )}
    </div>
  );
}
