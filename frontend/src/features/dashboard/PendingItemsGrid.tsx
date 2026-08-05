import { useEffect, useState } from "react";
import type { PendingItemsRow } from "./dashboard";
import { dashboardTableService } from "./dashboardTableService";

const COLUMNS: { key: keyof PendingItemsRow; abbr: string; title: string }[] = [
  { key: "incident", abbr: "IR/HOR", title: "Incident Report / HOR" },
  { key: "company", abbr: "CI", title: "Company Inspection" },
  { key: "internal", abbr: "IA", title: "Internal Audit" },
  { key: "external", abbr: "EA", title: "External Audit" },
  { key: "psc", abbr: "PSC", title: "PSC Inspection" },
  { key: "risk_assessment", abbr: "RA", title: "Risk Assessment" },
  { key: "sire", abbr: "SR", title: "SIRE" },
  { key: "non_sire", abbr: "N-SR", title: "Non-SIRE" },
  { key: "flag_state", abbr: "FS", title: "Flag State" },
  { key: "nc", abbr: "NC", title: "Non Conformities" },
  { key: "defect", abbr: "DL", title: "Defect List" },
  { key: "master_review", abbr: "M-R", title: "Master Review" },
  { key: "isps_review", abbr: "ISPS-R", title: "ISPS Review" },
];

/** Ported from admin/dashboard/dashboard_summary_dashlet.php. */
export function PendingItemsGrid() {
  const [rows, setRows] = useState<PendingItemsRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    dashboardTableService
      .fetchPendingItems()
      .then((data) => {
        if (!isMounted) return;
        setRows(data);
        setError(null);
      })
      .catch(() => {
        if (isMounted) setError("Couldn't load Pending Items. Please try again.");
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  return (
    <div className="flex flex-col gap-2">
      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-left text-sm">
          <thead>
            <tr className="border-b border-slate-200 bg-slate-50">
              <th className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600">VESSEL</th>
              {COLUMNS.map((column) => (
                <th
                  key={column.key}
                  title={column.title}
                  className="whitespace-nowrap px-2 py-1.5 text-center font-semibold text-slate-600"
                >
                  {column.abbr}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.vessel_id} className="border-b border-slate-100">
                <td className="px-2 py-1.5 text-slate-700">{row.vessel}</td>
                {COLUMNS.map((column) => {
                  const count = row[column.key] as number;
                  return (
                    <td key={column.key} className="px-2 py-1.5 text-center">
                      {count > 0 ? <span className="font-semibold text-red-600">{count}</span> : null}
                    </td>
                  );
                })}
              </tr>
            ))}
            {rows.length === 0 && !loading && !error && (
              <tr>
                <td colSpan={COLUMNS.length + 1} className="px-2 py-4 text-center text-sm text-slate-400">
                  No items.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {loading && (
        <div className="flex items-center gap-2 py-1 text-xs text-slate-400">
          <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" />
          Loading...
        </div>
      )}
      {error && <p className="text-xs text-red-600">{error}</p>}
    </div>
  );
}
