import { useEffect, useState } from "react";
import type { DrillDownParams } from "./kpiPscInspectionsService";
import type { KpiListResponse } from "./kpiPscInspections";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";

const PER_PAGE = 10;

interface KpiDrillDownModalProps {
  title: string;
  from?: string;
  to?: string;
  fetcher: (params: DrillDownParams) => Promise<KpiListResponse>;
  onClose: () => void;
}

/** Shared drill-down table for all three KPI charts — ported from the three view_kpi_psc_*.php modals. */
export function KpiDrillDownModal({ title, from, to, fetcher, onClose }: KpiDrillDownModalProps) {
  const [data, setData] = useState<KpiListResponse | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;
    setLoading(true);
    fetcher({ page, per_page: PER_PAGE, from, to })
      .then((result) => {
        if (!isMounted) return;
        setData(result);
        setError(null);
      })
      .catch(() => {
        if (isMounted) setError("Couldn't load this list. Please try again.");
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });
    return () => {
      isMounted = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page]);

  return (
    <Modal title={title} onClose={onClose}>
      <div className="flex flex-col gap-2">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50">
                {(data?.columns ?? []).map((column) => (
                  <th key={column.key} className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">
                    {column.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {(data?.rows ?? []).map((row, index) => (
                <tr key={index} className="border-b border-slate-100">
                  {(data?.columns ?? []).map((column) => (
                    <td key={column.key} className="px-2 py-1.5 text-slate-700">
                      {row[column.key] ?? "—"}
                    </td>
                  ))}
                </tr>
              ))}
              {data && data.rows.length === 0 && !loading && !error && (
                <tr>
                  <td colSpan={data.columns.length} className="px-2 py-6 text-center text-sm text-slate-400">
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

        {data && (
          <div className="flex items-center justify-between pt-2 text-xs text-slate-500">
            <span>{data.meta.total} total</span>
            <div className="flex items-center gap-2">
              <Button type="button" variant="secondary" className="!px-2 !py-0.5 text-xs" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                Prev
              </Button>
              <span>
                Page {data.meta.current_page} of {data.meta.last_page}
              </span>
              <Button
                type="button"
                variant="secondary"
                className="!px-2 !py-0.5 text-xs"
                disabled={page >= data.meta.last_page}
                onClick={() => setPage((p) => p + 1)}
              >
                Next
              </Button>
            </div>
          </div>
        )}
      </div>
    </Modal>
  );
}
