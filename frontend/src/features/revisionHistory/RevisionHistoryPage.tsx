import { useEffect, useState } from "react";
import { revisionHistoryService } from "./revisionHistoryService";
import type { RevisionHistoryDetail, RevisionHistoryOption, RevisionHistoryRow } from "./revisionHistory";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { RevisionHistoryForm } from "./RevisionHistoryForm";

const PER_PAGE = 10;

const COLUMNS = [
  { key: "arrangement", label: "ORDER", sortable: true },
  { key: "date_revised", label: "DATE", sortable: true },
  { key: "revision_no", label: "REVISION NO.", sortable: true },
  { key: "reference_no", label: "REF NO.", sortable: false },
  { key: "section", label: "SECTION", sortable: false },
  { key: "reason_revision", label: "REASON FOR REVISION", sortable: false },
  { key: "reviewed_by", label: "REVIEWED BY", sortable: true },
  { key: "approved_by", label: "APPROVED BY", sortable: true },
];

/** Ported from admin/revision_history/sms_revision_v.php. */
export function RevisionHistoryPage() {
  const [chapters, setChapters] = useState<RevisionHistoryOption[]>([]);

  const [chapterId, setChapterId] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");

  const [applied, setApplied] = useState({ chapterId: "", dateFrom: "", dateTo: "" });

  const [rows, setRows] = useState<RevisionHistoryRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("asc");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [dateError, setDateError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<RevisionHistoryDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [canCreateRecord, setCanCreateRecord] = useState(false);

  useEffect(() => {
    revisionHistoryService.options().then((data) => {
      setChapters(data.chapters);
      setCanCreateRecord(data.can_create_record);
    }).catch(() => undefined);
  }, []);

  useEffect(() => {
    setLoading(true);
    revisionHistoryService
      .list({
        chapter_id: applied.chapterId || undefined,
        date_from: applied.dateFrom || undefined,
        date_to: applied.dateTo || undefined,
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
      .catch(() => setError("Couldn't load Revision History records. Please try again."))
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
    if ((dateFrom || dateTo) && !(dateFrom && dateTo)) {
      setDateError("Please fill in both Date From and Date To, or leave both blank.");
      return;
    }
    setDateError(null);
    setApplied({ chapterId, dateFrom, dateTo });
    setPage(1);
  };

  const resetFilter = () => {
    setChapterId("");
    setDateFrom("");
    setDateTo("");
    setDateError(null);
    setApplied({ chapterId: "", dateFrom: "", dateTo: "" });
    setPage(1);
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openEdit = async (id: number | string) => {
    setActionError(null);
    const detail = await revisionHistoryService.show(id);
    setEditing(detail);
    setFormOpen(true);
  };

  const handleDelete = async (row: RevisionHistoryRow) => {
    if (!window.confirm("Are you sure you want to delete this item?")) return;
    setActionError(null);
    try {
      // row.id is always numeric here: this action is only reachable for can_delete records (local-only).
      await revisionHistoryService.destroy(row.id as number);
      reload();
    } catch {
      setActionError("Action failed. Please try again.");
    }
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">SMS Revision History</h1>
          {canCreateRecord && (
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
          )}
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
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
                {COLUMNS.map((column) => (
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
                <th className="px-2 py-1.5 font-semibold text-slate-600">ACTION</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-slate-100">
                  <td className="px-2 py-1.5 text-slate-700">{row.arrangement}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.date_revised}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.revision_no}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.reference_no || "—"}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.section || "—"}</td>
                  <td className="max-w-xs truncate px-2 py-1.5 text-slate-700" title={row.reason_revision ?? ""}>
                    {row.reason_revision || "—"}
                  </td>
                  <td className="px-2 py-1.5 text-slate-700">{row.reviewed_by}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.approved_by}</td>
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
                          onClick={() => handleDelete(row)}
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
                  <td colSpan={COLUMNS.length + 1} className="px-2 py-6 text-center text-sm text-slate-400">
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
        <Modal title={editing ? `Edit Revision — ${editing.revision_no}` : "Add Revision"} onClose={() => setFormOpen(false)}>
          <RevisionHistoryForm
            revision={editing ?? undefined}
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
