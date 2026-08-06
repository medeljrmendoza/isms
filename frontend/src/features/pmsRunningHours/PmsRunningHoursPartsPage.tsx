import { useEffect, useMemo, useState } from "react";
import { useLocation, useNavigate, useParams } from "react-router-dom";
import { pmsRunningHoursService } from "./pmsRunningHoursService";
import type { PmsRunningHoursPartRow, PmsRunningHoursPeriod } from "./pmsRunningHours";
import { Button } from "../../components/ui/Button";

function daysInMonth(month: number, year: number): number {
  return new Date(year, month, 0).getDate();
}

const MONTH_NAMES = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December",
];

type SortColumn = "part_code" | "part_name" | "since_delivery";
type SortDirection = "asc" | "desc";

/** Ported from the "View Parts" drill-down linked off the Running Hours component list (pms_running_hours_parts — not present in the checked-out legacy source mirror, reverse-engineered from the live site). */
export function PmsRunningHoursPartsPage() {
  const { vesselId, equipmentId } = useParams<{ vesselId: string; equipmentId: string }>();
  const location = useLocation();
  const navigate = useNavigate();
  const navState = location.state as { month?: number; year?: number } | null;

  const [currentPeriod, setCurrentPeriod] = useState<PmsRunningHoursPeriod | null>(null);
  const [equipmentCode, setEquipmentCode] = useState<string | null>(null);
  const [equipmentName, setEquipmentName] = useState<string | null>(null);
  const [rows, setRows] = useState<PmsRunningHoursPartRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [search, setSearch] = useState("");
  const [perPage, setPerPage] = useState(10);
  const [page, setPage] = useState(1);
  const [sortColumn, setSortColumn] = useState<SortColumn>("part_code");
  const [sortDirection, setSortDirection] = useState<SortDirection>("asc");

  const month = navState?.month;
  const year = navState?.year;

  useEffect(() => {
    if (!vesselId || !equipmentId) return;
    setLoading(true);
    pmsRunningHoursService
      .parts(vesselId, equipmentId, month, year)
      .then((data) => {
        setCurrentPeriod(data.current_period);
        setEquipmentCode(data.equipment_code);
        setEquipmentName(data.equipment_name);
        setRows(data.rows);
        setError(null);
      })
      .catch(() => setError("Couldn't load parts. Please try again."))
      .finally(() => setLoading(false));
  }, [vesselId, equipmentId, month, year]);

  const handleSort = (column: SortColumn) => {
    if (sortColumn === column) {
      setSortDirection((prev) => (prev === "asc" ? "desc" : "asc"));
    } else {
      setSortColumn(column);
      setSortDirection("asc");
    }
  };

  const filteredRows = useMemo(() => {
    const term = search.trim().toLowerCase();
    const filtered = term
      ? rows.filter((r) => r.part_code.toLowerCase().includes(term) || r.part_name.toLowerCase().includes(term))
      : rows;

    return [...filtered].sort((a, b) => {
      const cmp = sortColumn === "since_delivery" ? a.since_delivery - b.since_delivery : a[sortColumn].localeCompare(b[sortColumn]);
      return sortDirection === "asc" ? cmp : -cmp;
    });
  }, [rows, search, sortColumn, sortDirection]);

  const totalPages = Math.max(1, Math.ceil(filteredRows.length / perPage));
  const pagedRows = filteredRows.slice((page - 1) * perPage, page * perPage);

  const displayMonth = month ?? currentPeriod?.month;
  const displayYear = year ?? currentPeriod?.year;
  const dayCount = displayMonth && displayYear ? daysInMonth(displayMonth, displayYear) : 31;

  const sortIndicator = (column: SortColumn) => (sortColumn === column ? (sortDirection === "asc" ? " ▲" : " ▼") : "");

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">Running Hours (Part Lists)</h1>
          {currentPeriod && (
            <span className="rounded bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-700">
              Current Calendar: {MONTH_NAMES[currentPeriod.month - 1]} {currentPeriod.year}
            </span>
          )}
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
          <p className="text-sm text-slate-600">
            {displayMonth && displayYear ? `${MONTH_NAMES[displayMonth - 1]} ${displayYear}` : ""}
            {equipmentCode || equipmentName ? ` — ${equipmentCode ?? ""} ${equipmentName ?? ""}`.trim() : ""}
          </p>
          <Button type="button" variant="secondary" className="!px-3 !py-1.5 text-sm" onClick={() => navigate(-1)}>
            Back
          </Button>
        </div>

        {error && <p className="px-4 pt-2 text-sm text-red-600">{error}</p>}

        <div className="px-4 py-3">
          {loading ? (
            <div className="flex items-center gap-2 py-2 text-xs text-slate-400">
              <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" />
              Loading...
            </div>
          ) : (
            <>
              <div className="flex flex-wrap items-center justify-between gap-3 pb-2">
                <label className="flex items-center gap-2 text-xs text-slate-500">
                  Show
                  <select
                    value={perPage}
                    onChange={(e) => {
                      setPerPage(Number(e.target.value));
                      setPage(1);
                    }}
                    className="rounded-md border border-slate-300 px-2 py-1 text-sm"
                  >
                    {[10, 25, 50, 100].map((n) => (
                      <option key={n} value={n}>
                        {n}
                      </option>
                    ))}
                  </select>
                  entries
                </label>
                <label className="flex items-center gap-2 text-xs text-slate-500">
                  Search:
                  <input
                    type="search"
                    value={search}
                    onChange={(e) => {
                      setSearch(e.target.value);
                      setPage(1);
                    }}
                    className="rounded-md border border-slate-300 px-2 py-1 text-sm"
                  />
                </label>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 bg-slate-50">
                      <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">ACTIONS</th>
                      <th
                        onClick={() => handleSort("part_code")}
                        className="cursor-pointer select-none whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600 hover:text-slate-900"
                      >
                        PART CODE{sortIndicator("part_code")}
                      </th>
                      <th
                        onClick={() => handleSort("part_name")}
                        className="cursor-pointer select-none whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600 hover:text-slate-900"
                      >
                        PART NAME{sortIndicator("part_name")}
                      </th>
                      <th
                        onClick={() => handleSort("since_delivery")}
                        className="cursor-pointer select-none whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600 hover:text-slate-900"
                      >
                        TOTAL RUNNING HOURS SINCE DELIVERY{sortIndicator("since_delivery")}
                      </th>
                      <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">TOTAL RUNNING HOURS SINCE LAST ACTIVITY</th>
                      <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">DATE OF LAST ACTIVITY</th>
                      <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">DATE OF LAST RESET</th>
                      {Array.from({ length: dayCount }, (_, i) => i + 1).map((day) => (
                        <th key={day} className="px-1.5 py-1.5 text-center font-semibold text-slate-600">
                          {day}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {pagedRows.map((row) => (
                      <tr key={row.part_id} className="border-b border-slate-100">
                        <td className="whitespace-nowrap px-2 py-1.5"></td>
                        <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.part_code}</td>
                        <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.part_name}</td>
                        <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.since_delivery}</td>
                        <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.since_last_activity}</td>
                        <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.date_last_activity ?? ""}</td>
                        <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.date_last_reset ?? ""}</td>
                        {Array.from({ length: dayCount }, (_, i) => i + 1).map((day) => (
                          <td key={day} className="px-1.5 py-1.5 text-center text-slate-700">
                            {row.daily_hours[String(day)] ?? "0"}
                          </td>
                        ))}
                      </tr>
                    ))}
                    {pagedRows.length === 0 && (
                      <tr>
                        <td colSpan={dayCount + 7} className="px-2 py-6 text-center text-sm text-slate-400">
                          No parts tracked for this component.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              <div className="flex items-center justify-between pt-3 text-xs text-slate-500">
                <span>{filteredRows.length} total</span>
                <div className="flex items-center gap-2">
                  <Button type="button" variant="secondary" className="!px-2 !py-0.5 text-xs" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                    Prev
                  </Button>
                  <span>
                    Page {page} of {totalPages}
                  </span>
                  <Button
                    type="button"
                    variant="secondary"
                    className="!px-2 !py-0.5 text-xs"
                    disabled={page >= totalPages}
                    onClick={() => setPage((p) => p + 1)}
                  >
                    Next
                  </Button>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
