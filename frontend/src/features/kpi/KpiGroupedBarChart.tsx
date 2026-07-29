export interface KpiGroupedSeries {
  name: string;
  colorClass: string;
  hoverColorClass: string;
  values: number[];
  onBarClick: (index: number) => void;
}

/**
 * A two-series variant of KpiBarChart, built for KPI - SIRE vs Company
 * Inspections: legacy's own version of this page is entirely
 * Observations-based (comparing SIRE vs Company Inspection observation
 * counts, per chapter or per vessel), and no Observations module
 * exists anywhere in this migration. Reinterpreted (with explicit
 * sign-off) as a side-by-side comparison of SIRE vs Company Inspection
 * *report* counts per vessel instead — same visual language as
 * KpiBarChart, but each category renders one bar per series, each
 * independently clickable into its own drill-down.
 */
export function KpiGroupedBarChart({
  title,
  yAxisLabel,
  categories,
  series,
}: {
  title: string;
  yAxisLabel: string;
  categories: string[];
  series: KpiGroupedSeries[];
}) {
  const max = Math.max(1, ...series.flatMap((s) => s.values));

  return (
    <div className="flex flex-col gap-3">
      <h2 className="text-center text-sm font-bold text-slate-800">{title}</h2>

      <div className="flex items-center justify-center gap-4">
        {series.map((s) => (
          <span key={s.name} className="flex items-center gap-1.5 text-xs text-slate-600">
            <span className={`inline-block h-2.5 w-2.5 rounded-sm ${s.colorClass}`} />
            {s.name}
          </span>
        ))}
      </div>

      {categories.length === 0 ? (
        <p className="py-10 text-center text-sm text-slate-400">No data.</p>
      ) : (
        <div className="flex items-end gap-3 overflow-x-auto border-b border-slate-200 px-4 pb-1 pt-6" style={{ height: 340 }}>
          {categories.map((category, index) => (
            <div key={category} className="flex min-w-[88px] flex-1 items-end justify-center gap-1.5">
              {series.map((s) => (
                <button
                  key={s.name}
                  type="button"
                  onClick={() => s.onBarClick(index)}
                  className="group flex flex-1 flex-col items-center justify-end gap-1"
                  title={`${s.name} — ${category}: ${s.values[index] ?? 0}`}
                >
                  <span className="text-xs font-bold text-slate-700">{s.values[index] ?? 0}</span>
                  <div
                    className={`w-full max-w-[36px] rounded-t transition ${s.colorClass} ${s.hoverColorClass}`}
                    style={{ height: `${((s.values[index] ?? 0) / max) * 260 || 2}px`, minHeight: 2 }}
                  />
                </button>
              ))}
            </div>
          ))}
        </div>
      )}

      {categories.length > 0 && (
        <div className="flex gap-3 overflow-x-auto px-4 pb-4">
          {categories.map((category) => (
            <div key={category} className="flex min-w-[88px] flex-1 justify-center">
              <span
                className="inline-block origin-top-right whitespace-nowrap text-xs text-slate-600"
                style={{ transform: "rotate(-40deg)" }}
              >
                {category}
              </span>
            </div>
          ))}
        </div>
      )}

      <p className="text-center text-xs font-semibold text-slate-500">{yAxisLabel} (click a bar to view details)</p>
    </div>
  );
}
