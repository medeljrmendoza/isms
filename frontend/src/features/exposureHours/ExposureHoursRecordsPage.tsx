import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { exposureHoursService } from "./exposureHoursService";
import type { ExposureHoursRecordDetail, ExposureHoursRecordRow } from "./exposureHours";
import { Button } from "../../components/ui/Button";
import { Modal } from "../../components/ui/Modal";
import { ExposureHoursRecordForm } from "./ExposureHoursRecordForm";
import { ExposureHoursRecordViewModal } from "./ExposureHoursRecordViewModal";

const PER_PAGE = 10;

const COLUMNS = [
  { key: "added_by", label: "ADDED BY", sortable: true },
  { key: "date_from", label: "FROM", sortable: true },
  { key: "date_to", label: "TO", sortable: true },
  { key: "no_of_crew", label: "CREW", sortable: true },
  { key: "no_of_fat", label: "FAT", sortable: true },
  { key: "no_of_ptd", label: "PTD", sortable: true },
  { key: "no_of_ppd", label: "PPD", sortable: true },
  { key: "no_of_lwc", label: "LWC", sortable: true },
  { key: "no_of_rwc", label: "RWC", sortable: true },
  { key: "no_of_mtc", label: "MTC", sortable: true },
  { key: "total_hours", label: "TOTAL HOURS", sortable: true },
];

/** Ported from admin/exposurehours/records_v.php. */
export function ExposureHoursRecordsPage() {
  const { vesselId: vesselIdParam } = useParams<{ vesselId: string }>();
  const navigate = useNavigate();
  const vesselId = Number(vesselIdParam);

  const [rows, setRows] = useState<ExposureHoursRecordRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [appliedDateFrom, setAppliedDateFrom] = useState("");
  const [appliedDateTo, setAppliedDateTo] = useState("");
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("desc");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<ExposureHoursRecordDetail | null>(null);
  const [viewing, setViewing] = useState<ExposureHoursRecordDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [vesselName, setVesselName] = useState<string | null>(null);

  useEffect(() => {
    exposureHoursService.options().then((opts) => {
      const match = opts.vessels.find((v) => v.id === vesselId);
      setVesselName(match?.label ?? null);
    });
  }, [vesselId]);

  useEffect(() => {
    let isMounted = true;
    setLoading(true);

    exposureHoursService
      .list({
        vessel_id: vesselId,
        page,
        per_page: PER_PAGE,
        sort,
        direction,
        date_from: appliedDateFrom || undefined,
        date_to: appliedDateTo || undefined,
      })
      .then((data) => {
        if (!isMounted) return;
        setRows(data.rows);
        setLastPage(data.meta?.last_page ?? 1);
        setTotal(data.meta?.total ?? 0);
        setError(null);
      })
      .catch(() => {
        if (isMounted) setError("Couldn't load Exposure Hours records. Please try again.");
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [vesselId, page, sort, direction, appliedDateFrom, appliedDateTo, reloadKey]);

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
    setAppliedDateFrom(dateFrom);
    setAppliedDateTo(dateTo);
    setPage(1);
  };

  const resetFilters = () => {
    setDateFrom("");
    setDateTo("");
    setAppliedDateFrom("");
    setAppliedDateTo("");
    setPage(1);
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openView = async (id: number) => {
    setActionError(null);
    setViewing(await exposureHoursService.show(id));
  };

  const openEdit = async (id: number) => {
    setActionError(null);
    const detail = await exposureHoursService.show(id);
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
          <div className="flex items-center gap-2">
            <Button type="button" variant="secondary" className="!px-2 !py-1 text-xs" onClick={() => navigate("/exposure_hours")}>
              ← Summary
            </Button>
            <h1 className="text-base font-semibold text-slate-800">Exposure Hours — Records{vesselName ? ` — ${vesselName}` : ""}</h1>
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
            + Add Record
          </Button>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
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
                <th className="px-2 py-1.5 font-semibold text-slate-600">ACTIONS</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-slate-100">
                  <td className="px-2 py-1.5 text-slate-700">{row.added_by}</td>
                  <td className="px-2 py-1.5">
                    <button type="button" className="text-blue-600 hover:underline" onClick={() => openView(row.id)}>
                      {row.date_from}
                    </button>
                  </td>
                  <td className="px-2 py-1.5 text-slate-700">{row.date_to}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.no_of_crew}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.no_of_fat}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.no_of_ptd}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.no_of_ppd}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.no_of_lwc}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.no_of_rwc}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.no_of_mtc}</td>
                  <td className="px-2 py-1.5 text-slate-700">{row.total_hours}</td>
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
                            if (window.confirm(`Delete this record (${row.date_from} – ${row.date_to})?`)) {
                              runAction(() => exposureHoursService.destroy(row.id));
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
                  <td colSpan={COLUMNS.length + 1} className="px-2 py-6 text-center text-sm text-slate-400">
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
        <Modal title={editing ? "Edit Exposure Hours Record" : "Add Exposure Hours Record"} onClose={() => setFormOpen(false)}>
          <ExposureHoursRecordForm
            vesselId={vesselId}
            record={editing ?? undefined}
            onCancel={() => setFormOpen(false)}
            onSuccess={() => {
              setFormOpen(false);
              reload();
            }}
          />
        </Modal>
      )}

      {viewing && <ExposureHoursRecordViewModal record={viewing} onClose={() => setViewing(null)} />}
    </div>
  );
}
