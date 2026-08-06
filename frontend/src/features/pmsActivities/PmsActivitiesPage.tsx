import { useEffect, useState } from "react";
import { pmsActivitiesService } from "./pmsActivitiesService";
import { MONTH_LABELS } from "./pmsActivities";
import type { PmsActivityDetail, PmsActivityOption, PmsActivityOptions, PmsActivityRow, PmsTicketDetail } from "./pmsActivities";
import { Button } from "../../components/ui/Button";
import { MarkDoneModal } from "./MarkDoneModal";
import { PostponeModal } from "./PostponeModal";
import { ViewActivityModal } from "./ViewActivityModal";
import { ViewTicketModal } from "./ViewTicketModal";

const STATUS_COLOR: Record<string, string> = {
  overdue: "#FF0040",
  upcoming: "#2CBEF0",
  postponed: "#FFCC00",
};

function StatusDot({ status }: { status: PmsActivityRow["status"] }) {
  if (!status) return null;
  return <span className="inline-block h-4 w-4 rounded-full" style={{ backgroundColor: STATUS_COLOR[status] }} />;
}

function MonthCell({ month, onViewTicket }: { month: PmsActivityRow["months"][number]; onViewTicket: (ticketNo: string) => void }) {
  return (
    <div className="flex items-center justify-center gap-1">
      {month.done && (
        <button
          type="button"
          title={`Ticket ${month.done.ticket_no}`}
          onClick={() => onViewTicket(month.done!.ticket_no)}
          className="flex h-6 w-6 items-center justify-center rounded-full text-[11px] text-black"
          style={{ backgroundColor: "#01DF3A" }}
        >
          {month.done.day}
        </button>
      )}
      {month.postponed && (
        <button
          type="button"
          title={`Ticket ${month.postponed.ticket_no}`}
          onClick={() => onViewTicket(month.postponed!.ticket_no)}
          className="flex h-6 w-6 items-center justify-center rounded-full text-[11px] text-black"
          style={{ backgroundColor: "#FFCC00" }}
        >
          {month.postponed.day}
        </button>
      )}
    </div>
  );
}

/** Ported from admin/pms_activities/pms_activities_v.php. */
export function PmsActivitiesPage() {
  const [options, setOptions] = useState<PmsActivityOptions>({ vessels: [], departments: [], criticalities: [], main_groups: [] });

  const [vesselId, setVesselId] = useState("");
  const [appliedVesselId, setAppliedVesselId] = useState("");
  const [year, setYear] = useState("");
  const [mainGroupId, setMainGroupId] = useState("");
  const [criticalityId, setCriticalityId] = useState("");
  const [search, setSearch] = useState("");
  const [filterError, setFilterError] = useState<string | null>(null);

  const [currentPeriod, setCurrentPeriod] = useState<{ month: number; year: number } | null>(null);
  const [yearOptions, setYearOptions] = useState<number[]>([]);
  const [rows, setRows] = useState<PmsActivityRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [markDoneRow, setMarkDoneRow] = useState<PmsActivityRow | null>(null);
  const [postponeRow, setPostponeRow] = useState<PmsActivityRow | null>(null);
  const [viewingActivity, setViewingActivity] = useState<PmsActivityDetail | null>(null);
  const [viewingTicket, setViewingTicket] = useState<PmsTicketDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  useEffect(() => {
    pmsActivitiesService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (!appliedVesselId) return;
    setLoading(true);
    pmsActivitiesService
      .list({
        vessel_id: appliedVesselId,
        year: year ? Number(year) : undefined,
        main_group_id: mainGroupId || undefined,
        criticality_id: criticalityId || undefined,
        search: search || undefined,
      })
      .then((data) => {
        setCurrentPeriod(data.current_period);
        setYearOptions(data.year_options);
        setRows(data.rows);
        setError(null);
      })
      .catch(() => setError("Couldn't load activities. Please try again."))
      .finally(() => setLoading(false));
  }, [appliedVesselId, year, mainGroupId, criticalityId, search, reloadKey]);

  const applyFilter = () => {
    if (!vesselId) {
      setFilterError("Please select Vessel.");
      return;
    }
    setFilterError(null);
    setAppliedVesselId(vesselId);
    setYear("");
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openView = async (id: number | string) => {
    setActionError(null);
    try {
      setViewingActivity(await pmsActivitiesService.show(id));
    } catch {
      setActionError("Couldn't load this activity. Please try again.");
    }
  };

  const openTicket = async (ticketNo: string) => {
    setActionError(null);
    try {
      setViewingTicket(await pmsActivitiesService.ticket(ticketNo));
    } catch {
      setActionError("Couldn't load this ticket. Please try again.");
    }
  };

  const monthNumbers = Array.from({ length: 12 }, (_, i) => i + 1);

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">PMS Planned Maintenance</h1>
          <div className="flex items-center gap-3">
            {currentPeriod && (
              <span className="rounded bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-700">
                Current Calendar: {new Date(currentPeriod.year, currentPeriod.month - 1).toLocaleString(undefined, { month: "long" })}{" "}
                {currentPeriod.year}
              </span>
            )}
            <span className="flex items-center gap-3 text-xs text-slate-600">
              <b>LEGEND:</b>
              <span className="flex items-center gap-1">
                <span className="h-3 w-3 rounded-full" style={{ backgroundColor: STATUS_COLOR.overdue }} /> Overdue
              </span>
              <span className="flex items-center gap-1">
                <span className="h-3 w-3 rounded-full" style={{ backgroundColor: STATUS_COLOR.upcoming }} /> Upcoming in 30 Days
              </span>
              <span className="flex items-center gap-1">
                <span className="h-3 w-3 rounded-full" style={{ backgroundColor: STATUS_COLOR.postponed }} /> Postponed
              </span>
            </span>
          </div>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Vessel</label>
            <select value={vesselId} onChange={(e) => setVesselId(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">Select vessel...</option>
              {options.vessels.map((v: PmsActivityOption) => (
                <option key={v.id} value={v.id}>
                  {v.label}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Criticality</label>
            <select value={criticalityId} onChange={(e) => setCriticalityId(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">All</option>
              {options.criticalities.map((c: PmsActivityOption) => (
                <option key={c.id} value={c.id}>
                  {c.label}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Group</label>
            <select value={mainGroupId} onChange={(e) => setMainGroupId(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">All</option>
              {options.main_groups.map((g: PmsActivityOption) => (
                <option key={g.id} value={g.id}>
                  {g.label}
                </option>
              ))}
            </select>
          </div>
          {appliedVesselId && yearOptions.length > 0 && (
            <div className="flex flex-col gap-1">
              <label className="text-xs font-medium text-slate-500">Year</label>
              <select value={year} onChange={(e) => setYear(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">Select Year</option>
                {yearOptions.map((y) => (
                  <option key={y} value={y}>
                    {y}
                  </option>
                ))}
              </select>
            </div>
          )}
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={applyFilter}>
            Filter
          </Button>
          {filterError && <p className="text-sm text-red-600">{filterError}</p>}

          {appliedVesselId && (
            <div className="ml-auto flex flex-col gap-1">
              <label className="text-xs font-medium text-slate-500">Search</label>
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search activities..."
                className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
              />
            </div>
          )}
        </div>

        {actionError && <p className="px-4 pt-2 text-sm text-red-600">{actionError}</p>}
        {error && <p className="px-4 pt-2 text-sm text-red-600">{error}</p>}

        <div className="overflow-x-auto px-4 py-3">
          {!appliedVesselId ? (
            <p className="py-6 text-center text-sm text-slate-400">Select a vessel and click Filter.</p>
          ) : loading ? (
            <div className="flex items-center gap-2 py-2 text-xs text-slate-400">
              <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" />
              Loading...
            </div>
          ) : (
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50">
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">GROUP</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">DEPT</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">CODE</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">ACTIVITY</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">CRITICALITY</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">COMPONENT</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">PART</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">IN-CHARGE</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">FREQUENCY</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">TOTAL HOURS</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">LAST ACTIVITY</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">DUE DATE</th>
                  <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">STATUS</th>
                  {MONTH_LABELS.map((m) => (
                    <th key={m} className="px-1.5 py-1.5 text-center font-semibold text-slate-600">
                      {m}
                    </th>
                  ))}
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">ACTIONS</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-slate-100">
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.main_group ?? "—"}</td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.department ?? "—"}</td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.activity_code ?? "—"}</td>
                    <td className="whitespace-nowrap px-2 py-1.5">
                      <button type="button" className="text-sky-700 underline hover:text-sky-900" onClick={() => openView(row.id)}>
                        {row.activity_name}
                      </button>
                    </td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.criticality ?? "—"}</td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.equipment_name ?? "—"}</td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.part_name ?? "—"}</td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.incharge ?? "—"}</td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.frequency ?? "—"}</td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.total_hours ?? "—"}</td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.last_done ?? "—"}</td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.due_date ?? "—"}</td>
                    <td className="px-2 py-1.5 text-center">
                      <StatusDot status={row.status} />
                    </td>
                    {monthNumbers.map((m) => (
                      <td key={m} className="px-1 py-1.5">
                        <MonthCell month={row.months[m]} onViewTicket={openTicket} />
                      </td>
                    ))}
                    <td className="whitespace-nowrap px-2 py-1.5">
                      {!row.is_snapshot && (
                        <div className="flex gap-1">
                          <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => setMarkDoneRow(row)}>
                            Done
                          </Button>
                          <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => setPostponeRow(row)}>
                            Postpone
                          </Button>
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
                {rows.length === 0 && (
                  <tr>
                    <td colSpan={26} className="px-2 py-6 text-center text-sm text-slate-400">
                      No activities found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {markDoneRow && (
        <MarkDoneModal
          activity={markDoneRow}
          onClose={() => setMarkDoneRow(null)}
          onSuccess={() => {
            setMarkDoneRow(null);
            reload();
          }}
        />
      )}
      {postponeRow && (
        <PostponeModal
          activity={postponeRow}
          onClose={() => setPostponeRow(null)}
          onSuccess={() => {
            setPostponeRow(null);
            reload();
          }}
        />
      )}
      {viewingActivity && <ViewActivityModal activity={viewingActivity} onClose={() => setViewingActivity(null)} />}
      {viewingTicket && <ViewTicketModal ticket={viewingTicket} onClose={() => setViewingTicket(null)} />}
    </div>
  );
}
