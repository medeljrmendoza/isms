import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { pmsClassificationsService } from "./pmsClassificationsService";
import type { PmsSubClassificationRow } from "./pmsClassifications";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { PmsSubClassificationForm } from "./PmsSubClassificationForm";

const PER_PAGE = 10;

/** Ported from admin/pms_setup_classification/pms_setup_sub_classification_v.php. */
export function PmsSubClassificationsPage() {
  const { classificationId } = useParams<{ classificationId: string }>();
  const navigate = useNavigate();
  const classId = Number(classificationId);

  const [classificationName, setClassificationName] = useState("");
  const [rows, setRows] = useState<PmsSubClassificationRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<PmsSubClassificationRow | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    if (!classId) return;

    setLoading(true);
    pmsClassificationsService
      .subList(classId, { page, per_page: PER_PAGE })
      .then((data) => {
        setClassificationName(data.classification.name);
        setRows(data.rows);
        setLastPage(data.meta.last_page);
        setTotal(data.meta.total);
        setError(null);
      })
      .catch(() => setError("Couldn't load sub-classifications. Please try again."))
      .finally(() => setLoading(false));
  }, [classId, page, reloadKey]);

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
          <div>
            <button type="button" onClick={() => navigate("/pms_setup_classification")} className="text-xs text-sky-600 hover:underline">
              ← Back to Classifications
            </button>
            <h1 className="text-base font-semibold text-slate-800">Sub-Classifications — {classificationName}</h1>
          </div>
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

        {actionError && <p className="px-4 pt-2 text-sm text-red-600">{actionError}</p>}

        <div className="overflow-x-auto px-4 py-3">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50">
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">CHART CODE</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">SUB-CLASSIFICATION</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">DESCRIPTION</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">STATUS</th>
                <th className="px-2 py-1.5 font-semibold text-slate-600">ACTIONS</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-slate-100">
                  <td className="px-2 py-1.5 text-slate-700">{row.chart_code}</td>
                  <td className="px-2 py-1.5 font-medium text-slate-800">{row.name}</td>
                  <td className="max-w-md px-2 py-1.5 text-slate-700">{row.description ?? "—"}</td>
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
                        onClick={() => runAction(() => pmsClassificationsService.subToggleStatus(row.id))}
                      >
                        {row.is_active ? "Inactivate" : "Activate"}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && !loading && !error && (
                <tr>
                  <td colSpan={5} className="px-2 py-6 text-center text-sm text-slate-400">
                    No sub-classifications.
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
        <Modal title={editing ? `Edit Sub-Classification — ${editing.name}` : "Add Sub-Classification"} onClose={() => setFormOpen(false)}>
          <PmsSubClassificationForm
            classificationId={classId}
            classificationName={classificationName}
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
