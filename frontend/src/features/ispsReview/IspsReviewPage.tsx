import { useEffect, useState } from "react";
import { ispsReviewService } from "./ispsReviewService";
import type { IspsReviewDetail, IspsReviewOption, IspsReviewRow } from "./ispsReview";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { IspsReviewForm } from "./IspsReviewForm";

const PER_PAGE = 10;

const MODULE_COLUMNS = [
  { key: "vessel", label: "VESSEL", sortable: false },
  { key: "review_date", label: "DATE", sortable: true },
  { key: "added_by", label: "ADDED BY", sortable: true },
  { key: "review_quarter", label: "QTR", sortable: true },
  { key: "review_year", label: "YR", sortable: true },
  { key: "sms", label: "PROCEDURE", sortable: false },
  { key: "review_recommendation", label: "RECOMMENDATION", sortable: false },
  { key: "has_vessel_remarks", label: "VESSEL REMARKS", sortable: false },
  { key: "has_shore_remarks", label: "SHORE REMARKS", sortable: false },
  { key: "shore_status", label: "STATUS", sortable: true },
];

const STATUS_OPTIONS = ["APPROVED", "RECOMMEND APPROVAL", "DISAPPROVED", "DISREGARD"];

function StatusBadge({ status }: { status: string }) {
  if (!status) return <span className="text-xs font-semibold text-amber-600">PENDING</span>;
  const color =
    status === "APPROVED"
      ? "text-emerald-600"
      : status === "DISAPPROVED" || status === "DISREGARD"
        ? "text-red-600"
        : "text-sky-600";
  return <span className={`text-xs font-semibold ${color}`}>{status}</span>;
}

/** Ported from admin/isps_review/isps_review_v.php. */
export function IspsReviewPage() {
  const [vessels, setVessels] = useState<IspsReviewOption[]>([]);
  const [chapters, setChapters] = useState<IspsReviewOption[]>([]);

  const [vesselId, setVesselId] = useState("");
  const [startQuarter, setStartQuarter] = useState("");
  const [startYear, setStartYear] = useState("");
  const [endQuarter, setEndQuarter] = useState("");
  const [endYear, setEndYear] = useState("");
  const [recordStatus, setRecordStatus] = useState("");
  const [chapterId, setChapterId] = useState("");

  const [applied, setApplied] = useState({
    vesselId: "",
    startQuarter: "",
    startYear: "",
    endQuarter: "",
    endYear: "",
    recordStatus: "",
    chapterId: "",
  });

  const [rows, setRows] = useState<IspsReviewRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("desc");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [dateError, setDateError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<IspsReviewDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    ispsReviewService.options().then((data) => {
      setVessels(data.vessels);
      setChapters(data.chapters);
    }).catch(() => undefined);
  }, []);

  useEffect(() => {
    setLoading(true);
    ispsReviewService
      .list({
        vessel_id: applied.vesselId ? Number(applied.vesselId) : undefined,
        start_quarter: applied.startQuarter ? Number(applied.startQuarter) : undefined,
        start_year: applied.startYear ? Number(applied.startYear) : undefined,
        end_quarter: applied.endQuarter ? Number(applied.endQuarter) : undefined,
        end_year: applied.endYear ? Number(applied.endYear) : undefined,
        record_status: applied.recordStatus || undefined,
        chapter_id: applied.chapterId ? Number(applied.chapterId) : undefined,
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
      .catch(() => setError("Couldn't load ISPS Review records. Please try again."))
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
    if ((startQuarter || startYear || endQuarter || endYear) && !(startQuarter && startYear && endQuarter && endYear)) {
      setDateError("Please fill in both Start and End Quarter/Year, or leave all four blank.");
      return;
    }
    setDateError(null);
    setApplied({ vesselId, startQuarter, startYear, endQuarter, endYear, recordStatus, chapterId });
    setPage(1);
  };

  const resetFilter = () => {
    setVesselId("");
    setStartQuarter("");
    setStartYear("");
    setEndQuarter("");
    setEndYear("");
    setRecordStatus("");
    setChapterId("");
    setDateError(null);
    setApplied({ vesselId: "", startQuarter: "", startYear: "", endQuarter: "", endYear: "", recordStatus: "", chapterId: "" });
    setPage(1);
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openEdit = async (id: number) => {
    setActionError(null);
    const detail = await ispsReviewService.show(id);
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
          <h1 className="text-base font-semibold text-slate-800">SMS ISPS Review</h1>
          <Button
            type="button"
            variant="success"
            className="!px-3 !py-1.5 text-sm"
            onClick={() => {
              setEditing(null);
              setFormOpen(true);
            }}
          >
            + Add Record
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
            <label className="text-xs font-medium text-slate-500">Start Qtr</label>
            <select value={startQuarter} onChange={(e) => setStartQuarter(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value=""></option>
              {[1, 2, 3, 4].map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Start Year</label>
            <input
              type="number"
              value={startYear}
              onChange={(e) => setStartYear(e.target.value)}
              className="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">End Qtr</label>
            <select value={endQuarter} onChange={(e) => setEndQuarter(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value=""></option>
              {[1, 2, 3, 4].map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">End Year</label>
            <input
              type="number"
              value={endYear}
              onChange={(e) => setEndYear(e.target.value)}
              className="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Status</label>
            <select value={recordStatus} onChange={(e) => setRecordStatus(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">All</option>
              {STATUS_OPTIONS.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Manual</label>
            <select value={chapterId} onChange={(e) => setChapterId(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">All</option>
              {chapters.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.label}
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
          {dateError && <p className="text-sm text-red-600">{dateError}</p>}
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
                  <td className="px-2 py-1.5 text-slate-700">{row.vessel || "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.review_date}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.added_by}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.review_quarter}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.review_year}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.sms || "—"}</td>
                  <td className="max-w-xs truncate px-2 py-1.5 text-slate-700" title={row.review_recommendation ?? ""}>
                    {row.review_recommendation || "—"}
                  </td>
                  <td className="px-2 py-1.5 text-center">{row.has_vessel_remarks ? "✓" : "—"}</td>
                  <td className="px-2 py-1.5 text-center">{row.has_shore_remarks ? "✓" : "—"}</td>
                  <td className="px-2 py-1.5">
                    <StatusBadge status={row.shore_status} />
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="flex flex-wrap gap-1">
                      {row.can_edit && (
                        <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => openEdit(row.id)}>
                          Edit
                        </Button>
                      )}
                      {row.can_approve && (
                        <Button
                          type="button"
                          variant="success"
                          className="!px-1.5 !py-0.5 text-xs"
                          onClick={() => runAction(() => ispsReviewService.approve(row.id))}
                        >
                          Approve
                        </Button>
                      )}
                      {row.can_recommend_approval && (
                        <Button
                          type="button"
                          variant="success"
                          className="!px-1.5 !py-0.5 text-xs"
                          onClick={() => runAction(() => ispsReviewService.recommendApproval(row.id))}
                        >
                          Recommend
                        </Button>
                      )}
                      {row.can_disapprove && (
                        <Button
                          type="button"
                          variant="secondary"
                          className="!px-1.5 !py-0.5 text-xs text-red-600"
                          onClick={() => runAction(() => ispsReviewService.disapprove(row.id))}
                        >
                          Disapprove
                        </Button>
                      )}
                      {row.can_disregard && (
                        <Button
                          type="button"
                          variant="secondary"
                          className="!px-1.5 !py-0.5 text-xs text-red-600"
                          onClick={() => runAction(() => ispsReviewService.disregard(row.id))}
                        >
                          Disregard
                        </Button>
                      )}
                      {row.can_reopen && (
                        <Button
                          type="button"
                          variant="secondary"
                          className="!px-1.5 !py-0.5 text-xs"
                          onClick={() => runAction(() => ispsReviewService.reopen(row.id))}
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
                            if (window.confirm(`Delete this review (${row.sms || row.review_date})?`)) {
                              runAction(() => ispsReviewService.destroy(row.id));
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
        <Modal title={editing ? `Edit Review — ${editing.sms || editing.review_date}` : "Add ISPS Review"} onClose={() => setFormOpen(false)}>
          <IspsReviewForm
            review={editing ?? undefined}
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
