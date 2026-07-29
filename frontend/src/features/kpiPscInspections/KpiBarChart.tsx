import type { KpiSummaryRow } from "./kpiPscInspections";

/**
 * Ported from kpi_psc_inspections_v.php's Highcharts column charts.
 * No chart library exists in this project, so this is a small
 * hand-rolled bar chart: same interaction (click a bar to drill down),
 * same shape (vertical bars, count label on top, category below).
 */
export function KpiBarChart({
  title,
  yAxisLabel,
  rows,
  onBarClick,
}: {
  title: string;
  yAxisLabel: string;
  rows: KpiSummaryRow[];
  onBarClick: (row: KpiSummaryRow) => void;
}) {
  const max = Math.max(1, ...rows.map((r) => r.count));

  return (
    <div className="flex flex-col gap-3">
      <h2 className="text-center text-sm font-bold text-slate-800">{title}</h2>

      {rows.length === 0 ? (
        <p className="py-10 text-center text-sm text-slate-400">No data.</p>
      ) : (
        <div className="flex items-end gap-3 overflow-x-auto border-b border-slate-200 px-4 pb-1 pt-6" style={{ height: 340 }}>
          {rows.map((row) => (
            <button
              key={row.label}
              type="button"
              onClick={() => onBarClick(row)}
              className="group flex min-w-[56px] flex-1 flex-col items-center justify-end gap-1"
              title={`${row.label}: ${row.count}`}
            >
              <span className="text-xs font-bold text-slate-700">{row.count}</span>
              <div
                className="w-full max-w-[48px] rounded-t bg-sky-500 transition group-hover:bg-sky-600"
                style={{ height: `${(row.count / max) * 260 || 2}px`, minHeight: 2 }}
              />
            </button>
          ))}
        </div>
      )}

      {rows.length > 0 && (
        <div className="flex gap-3 overflow-x-auto px-4 pb-4">
          {rows.map((row) => (
            <div key={row.label} className="flex min-w-[56px] flex-1 justify-center">
              <span
                className="inline-block origin-top-right whitespace-nowrap text-xs text-slate-600"
                style={{ transform: "rotate(-40deg)" }}
              >
                {row.label}
              </span>
            </div>
          ))}
        </div>
      )}

      <p className="text-center text-xs font-semibold text-slate-500">{yAxisLabel} (click a bar to view details)</p>
    </div>
  );
}
