import { useEffect, useState } from "react";
import { riskAssessmentShoreService } from "./riskAssessmentShoreService";
import type { RiskAssessmentShoreDetail, RiskAssessmentShoreOptions, RiskAssessmentShoreRow } from "./riskAssessmentShore";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { RiskAssessmentShoreViewModal } from "./RiskAssessmentShoreViewModal";
import { RiskAssessmentShoreForm } from "./RiskAssessmentShoreForm";

const PER_PAGE = 10;

function ApprovalBadge({ required, approved }: { required: boolean; approved: boolean }) {
  if (!required) return <span className="text-slate-400">N/A</span>;
  return approved ? (
    <span className="font-semibold text-emerald-600">✓ Approved</span>
  ) : (
    <span className="font-semibold text-amber-600">Pending</span>
  );
}

/** Ported from admin/riskassessmentshore/risk_assessment_v.php. */
export function RiskAssessmentShoreListPage() {
  const [options, setOptions] = useState<RiskAssessmentShoreOptions>({ vessels: [], categories: [], operations: [], years: [] });
  const [vesselId, setVesselId] = useState<string>("");
  const [year, setYear] = useState<string>("");
  const [appliedVesselId, setAppliedVesselId] = useState<number | string | null>(null);
  const [appliedYear, setAppliedYear] = useState<number | null>(null);

  const [rows, setRows] = useState<RiskAssessmentShoreRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<RiskAssessmentShoreDetail | null>(null);
  const [viewing, setViewing] = useState<RiskAssessmentShoreDetail | null>(null);

  useEffect(() => {
    riskAssessmentShoreService.options().then(setOptions);
  }, []);

  useEffect(() => {
    setLoading(true);
    riskAssessmentShoreService
      .list({
        vessel_id: appliedVesselId ?? undefined,
        year: appliedYear ?? undefined,
        page,
        per_page: PER_PAGE,
      })
      .then((data) => {
        setRows(data.rows);
        setLastPage(data.meta?.last_page ?? 1);
        setTotal(data.meta?.total ?? 0);
        setError(null);
      })
      .catch(() => setError("Couldn't load Risk Assessment (Shore) reports. Please try again."))
      .finally(() => setLoading(false));
  }, [appliedVesselId, appliedYear, page, reloadKey]);

  const applyFilter = () => {
    setAppliedVesselId(vesselId || null);
    setAppliedYear(year ? Number(year) : null);
    setPage(1);
  };

  const resetFilter = () => {
    setVesselId("");
    setYear("");
    setAppliedVesselId(null);
    setAppliedYear(null);
    setPage(1);
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openView = async (id: number | string) => setViewing(await riskAssessmentShoreService.show(id));
  const openEdit = async (id: number | string) => {
    setEditing(await riskAssessmentShoreService.show(id));
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
          <h1 className="text-base font-semibold text-slate-800">Risk Assessment Reports (Shore)</h1>
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
            <select value={vesselId} onChange={(e) => setVesselId(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">All vessels</option>
              {options.vessels.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.label}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Year</label>
            <select value={year} onChange={(e) => setYear(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">All years</option>
              {options.years.map((y) => (
                <option key={y} value={y}>
                  {y}
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
        </div>

        {actionError && <p className="px-4 pt-2 text-sm text-red-600">{actionError}</p>}

        <div className="overflow-x-auto px-4 py-3">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50">
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">REPORT NO.</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">TYPE</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">VESSEL</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">DATE</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">PORT/LOCATION</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">CATEGORY</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">TASK</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">APPROVED BY TECHNICAL?</th>
                <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">APPROVED BY MARINE?</th>
                <th className="px-2 py-1.5 font-semibold text-slate-600">ACTIONS</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-slate-100">
                  <td className="px-2 py-1.5">
                    <button type="button" className="text-blue-600 hover:underline" onClick={() => openView(row.id)}>
                      {row.report_no}
                    </button>
                  </td>
                  <td className="px-2 py-1.5 text-slate-700">{row.report_type}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.vessel || "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.risk_date}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.port}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.category}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.task}</td>
                  <td className="px-2 py-1.5">
                    <ApprovalBadge required={row.approval_from_shore} approved={row.shore_is_approved} />
                  </td>
                  <td className="px-2 py-1.5">
                    <ApprovalBadge required={row.approval_from_marine} approved={row.marine_is_approved} />
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
                            if (window.confirm(`Delete report ${row.report_no}?`)) {
                              // row.id is always numeric here: can_delete is only true for local rows.
                              runAction(() => riskAssessmentShoreService.destroy(row.id as number));
                            }
                          }}
                        >
                          Delete
                        </Button>
                      )}
                      {row.can_reopen && (
                        <Button
                          type="button"
                          variant="secondary"
                          className="!px-1.5 !py-0.5 text-xs text-amber-600"
                          // row.id is always numeric here: can_reopen is only true for local rows.
                          onClick={() => runAction(() => riskAssessmentShoreService.reopen(row.id as number))}
                        >
                          Re-open
                        </Button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && !loading && !error && (
                <tr>
                  <td colSpan={10} className="px-2 py-6 text-center text-sm text-slate-400">
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

      {viewing && <RiskAssessmentShoreViewModal report={viewing} onClose={() => setViewing(null)} />}

      {formOpen && (
        <Modal title={editing ? `Edit Risk Assessment Report (Shore) — ${editing.report_no}` : "Add Risk Assessment Report (Shore)"} onClose={() => setFormOpen(false)}>
          <RiskAssessmentShoreForm
            report={editing}
            options={options}
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
