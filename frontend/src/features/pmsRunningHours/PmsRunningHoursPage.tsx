import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { pmsRunningHoursService } from "./pmsRunningHoursService";
import type { PmsRunningHoursOption, PmsRunningHoursPeriod, PmsRunningHoursRow } from "./pmsRunningHours";
import { Button } from "../../components/ui/Button";

function daysInMonth(month: number, year: number): number {
  return new Date(year, month, 0).getDate();
}

const MONTH_NAMES = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December",
];

type SortColumn = "equipment_code" | "equipment_name" | "since_delivery";
type SortDirection = "asc" | "desc";

/** Ported from admin/pms_running_hours_equipments/running_hours_v.php (verified against the live legacy site — the checked-out static view file is stale). */
export function PmsRunningHoursPage() {
  const navigate = useNavigate();
  const [vessels, setVessels] = useState<PmsRunningHoursOption[]>([]);
  const [vesselId, setVesselId] = useState("");
  const [appliedVesselId, setAppliedVesselId] = useState("");
  const [filterError, setFilterError] = useState<string | null>(null);

  const [currentPeriod, setCurrentPeriod] = useState<PmsRunningHoursPeriod | null>(null);
  const [periodOptions, setPeriodOptions] = useState<PmsRunningHoursPeriod[]>([]);
  const [selectedMonth, setSelectedMonth] = useState<number | "">("");
  const [selectedYear, setSelectedYear] = useState<number | "">("");

  const [rows, setRows] = useState<PmsRunningHoursRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [search, setSearch] = useState("");
  const [perPage, setPerPage] = useState(10);
  const [page, setPage] = useState(1);
  const [sortColumn, setSortColumn] = useState<SortColumn>("equipment_code");
  const [sortDirection, setSortDirection] = useState<SortDirection>("asc");

  useEffect(() => {
    pmsRunningHoursService.options().then((data) => setVessels(data.vessels)).catch(() => undefined);
  }, []);

  const load = (vId: number | string, month?: number, year?: number) => {
    setLoading(true);
    pmsRunningHoursService
      .list(vId, month, year)
      .then((data) => {
        setCurrentPeriod(data.current_period);
        setPeriodOptions(data.period_options);
        setRows(data.rows);
        setSelectedMonth(month ?? data.current_period?.month ?? "");
        setSelectedYear(year ?? data.current_period?.year ?? "");
        setPage(1);
        setError(null);
      })
      .catch(() => setError("Couldn't load running hours. Please try again."))
      .finally(() => setLoading(false));
  };

  const handleFilter = () => {
    if (!vesselId) {
      setFilterError("Please select Vessel.");
      return;
    }
    setFilterError(null);
    const sameVessel = vesselId === appliedVesselId;
    setAppliedVesselId(vesselId);
    load(vesselId, sameVessel && selectedMonth !== "" ? selectedMonth : undefined, sameVessel && selectedYear !== "" ? selectedYear : undefined);
  };

  const handlePrint = () => {
    window.print();
  };

  const yearOptions = useMemo(() => {
    const years = new Set(periodOptions.map((p) => p.year));
    if (currentPeriod) years.add(currentPeriod.year);
    return Array.from(years).sort((a, b) => b - a);
  }, [periodOptions, currentPeriod]);

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
      ? rows.filter((r) => r.equipment_code.toLowerCase().includes(term) || r.equipment_name.toLowerCase().includes(term))
      : rows;

    const sorted = [...filtered].sort((a, b) => {
      let cmp = 0;
      if (sortColumn === "since_delivery") {
        cmp = (a.since_delivery ?? -1) - (b.since_delivery ?? -1);
      } else {
        cmp = a[sortColumn].localeCompare(b[sortColumn]);
      }
      return sortDirection === "asc" ? cmp : -cmp;
    });

    return sorted;
  }, [rows, search, sortColumn, sortDirection]);

  const totalPages = Math.max(1, Math.ceil(filteredRows.length / perPage));
  const pagedRows = filteredRows.slice((page - 1) * perPage, page * perPage);

  const dayCount = selectedMonth && selectedYear ? daysInMonth(selectedMonth, selectedYear) : 31;
  const isViewingCurrent = !!currentPeriod && selectedMonth === currentPeriod.month && selectedYear === currentPeriod.year;

  const sortIndicator = (column: SortColumn) => (sortColumn === column ? (sortDirection === "asc" ? " ▲" : " ▼") : "");

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">Running Hours (Component Lists)</h1>
          {currentPeriod && (
            <span className="rounded bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-700">
              Current Calendar: {MONTH_NAMES[currentPeriod.month - 1]} {currentPeriod.year}
            </span>
          )}
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
            <label className="text-xs font-medium text-slate-500">Month</label>
            <select
              value={selectedMonth}
              onChange={(e) => setSelectedMonth(e.target.value ? Number(e.target.value) : "")}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">Select Month</option>
              {MONTH_NAMES.map((name, i) => (
                <option key={name} value={i + 1}>
                  {name}
                </option>
              ))}
            </select>
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Year</label>
            <select
              value={selectedYear}
              onChange={(e) => setSelectedYear(e.target.value ? Number(e.target.value) : "")}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">Select Year</option>
              {yearOptions.map((y) => (
                <option key={y} value={y}>
                  {y}
                </option>
              ))}
            </select>
          </div>

          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={handleFilter}>
            Filter
          </Button>
          {appliedVesselId && (
            <Button type="button" variant="secondary" className="!px-3 !py-1.5 text-sm" onClick={handlePrint}>
              Print
            </Button>
          )}
          {filterError && <p className="text-sm text-red-600">{filterError}</p>}
        </div>

        {error && <p className="px-4 pt-2 text-sm text-red-600">{error}</p>}

        <div className="px-4 py-3">
          {!appliedVesselId ? (
            <p className="py-6 text-center text-sm text-slate-400">Select Vessel, Month, and Year, then click Filter.</p>
          ) : loading ? (
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
                        onClick={() => handleSort("equipment_code")}
                        className="cursor-pointer select-none whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600 hover:text-slate-900"
                      >
                        COMPONENT CODE{sortIndicator("equipment_code")}
                      </th>
                      <th
                        onClick={() => handleSort("equipment_name")}
                        className="cursor-pointer select-none whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600 hover:text-slate-900"
                      >
                        COMPONENT{sortIndicator("equipment_name")}
                      </th>
                      <th
                        onClick={() => handleSort("since_delivery")}
                        className="cursor-pointer select-none whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600 hover:text-slate-900"
                      >
                        TOTAL RUNNING HOURS SINCE DELIVERY{sortIndicator("since_delivery")}
                      </th>
                      <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">MONTHLY RUNNING HOURS</th>
                      {Array.from({ length: dayCount }, (_, i) => i + 1).map((day) => (
                        <th key={day} className="px-1.5 py-1.5 text-center font-semibold text-slate-600">
                          {day}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {pagedRows.map((row) => (
                      <tr key={row.equipment_id} className="border-b border-slate-100">
                        <td className="whitespace-nowrap px-2 py-1.5"></td>
                        <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.equipment_code}</td>
                        <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">
                          {typeof row.equipment_id === "string" ? (
                            <button
                              type="button"
                              className="text-sky-700 hover:underline"
                              onClick={() =>
                                navigate(`/pms_running_hours_equipments/${appliedVesselId}/parts/${row.equipment_id}`, {
                                  state: { month: selectedMonth, year: selectedYear },
                                })
                              }
                            >
                              {row.equipment_name}
                            </button>
                          ) : (
                            row.equipment_name
                          )}
                        </td>
                        <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.since_delivery ?? ""}</td>
                        <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.monthly_rh ?? ""}</td>
                        {Array.from({ length: dayCount }, (_, i) => i + 1).map((day) => (
                          <td key={day} className="px-1.5 py-1.5 text-center text-slate-700">
                            {row.daily_hours[String(day)] ?? ""}
                          </td>
                        ))}
                      </tr>
                    ))}
                    {pagedRows.length === 0 && (
                      <tr>
                        <td colSpan={dayCount + 5} className="px-2 py-6 text-center text-sm text-slate-400">
                          No components tracked for this vessel.
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

              {!isViewingCurrent && (
                <p className="pt-2 text-xs text-slate-400">Viewing a past month's snapshot.</p>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
