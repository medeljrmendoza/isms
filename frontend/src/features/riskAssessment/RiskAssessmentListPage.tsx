import { useEffect, useState } from "react";
import { riskAssessmentService } from "./riskAssessmentService";
import type { RiskAssessmentDetail, RiskAssessmentOption, RiskAssessmentRow } from "./riskAssessment";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { RiskAssessmentViewModal } from "./RiskAssessmentViewModal";
import { RiskAssessmentApprovalForm } from "./RiskAssessmentApprovalForm";

const PER_PAGE = 10;

function ApprovalBadge({ required, approved }: { required: boolean; approved: boolean }) {
  if (!required) return <span className="text-slate-400">N/A</span>;
  return approved ? (
    <span className="font-semibold text-emerald-600">✓ Approved</span>
  ) : (
    <span className="font-semibold text-amber-600">Pending</span>
  );
}

/** Ported from admin/riskassessmentvessel/risk_assessment_v.php. */
export function RiskAssessmentListPage() {
  const [vessels, setVessels] = useState<RiskAssessmentOption[]>([]);
  const [years, setYears] = useState<number[]>([]);
  const [vesselId, setVesselId] = useState<string>("");
  const [year, setYear] = useState<string>("");
  const [appliedVesselId, setAppliedVesselId] = useState<number | string | null>(null);
  const [appliedYear, setAppliedYear] = useState<number | null>(null);

  const [rows, setRows] = useState<RiskAssessmentRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [viewing, setViewing] = useState<RiskAssessmentDetail | null>(null);
  const [editing, setEditing] = useState<RiskAssessmentDetail | null>(null);

  useEffect(() => {
    riskAssessmentService.options().then((data) => {
      setVessels(data.vessels);
      setYears(data.years);
    });
  }, []);

  useEffect(() => {
    if (appliedVesselId === null || appliedYear === null) return;

    setLoading(true);
    riskAssessmentService
      .list({ vessel_id: appliedVesselId, year: appliedYear, page, per_page: PER_PAGE })
      .then((data) => {
        setRows(data.rows);
        setLastPage(data.meta?.last_page ?? 1);
        setTotal(data.meta?.total ?? 0);
        setError(null);
      })
      .catch(() => setError("Couldn't load Risk Assessment reports. Please try again."))
      .finally(() => setLoading(false));
  }, [appliedVesselId, appliedYear, page, reloadKey]);

  const applyFilter = () => {
    if (!vesselId || !year) return;
    setAppliedVesselId(vesselId);
    setAppliedYear(Number(year));
    setPage(1);
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openView = async (id: number | string) => setViewing(await riskAssessmentService.show(id));
  const openEdit = async (id: number | string) => setEditing(await riskAssessmentService.show(id));

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">Risk Assessment Reports</h1>
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
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Year</label>
            <select value={year} onChange={(e) => setYear(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">Select year...</option>
              {years.map((y) => (
                <option key={y} value={y}>
                  {y}
                </option>
              ))}
            </select>
          </div>
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={applyFilter} disabled={!vesselId || !year}>
            Filter
          </Button>
        </div>

        <div className="overflow-x-auto px-4 py-3">
          {appliedVesselId === null ? (
            <p className="py-6 text-center text-sm text-slate-400">Select a vessel and year, then click Filter.</p>
          ) : (
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50">
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">REPORT NO.</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">VESSEL</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">DATE</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">PORT</th>
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
                    <td className="px-2 py-1.5 text-slate-700">{row.vessel}</td>
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
                    <td colSpan={9} className="px-2 py-6 text-center text-sm text-slate-400">
                      No items.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          )}

          {loading && (
            <div className="flex items-center gap-2 py-2 text-xs text-slate-400">
              <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" />
              Loading...
            </div>
          )}
          {error && <p className="text-xs text-red-600">{error}</p>}

          {appliedVesselId !== null && (
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
          )}
        </div>
      </div>

      {viewing && <RiskAssessmentViewModal report={viewing} onClose={() => setViewing(null)} />}

      {editing && (
        <Modal title={`Process Approval — ${editing.report_no}`} onClose={() => setEditing(null)}>
          <RiskAssessmentApprovalForm
            report={editing}
            onCancel={() => setEditing(null)}
            onSuccess={() => {
              setEditing(null);
              reload();
            }}
          />
        </Modal>
      )}
    </div>
  );
}
