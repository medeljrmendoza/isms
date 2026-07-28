import { useEffect, useState } from "react";
import type { DashletColumn, TableRow } from "../../types/dashboard";
import { dashboardTableService } from "../../api/dashboardTableService";
import { Button } from "../ui/Button";

const PER_PAGE = 10;

/**
 * A self-contained interactive table: search, sortable headers,
 * pagination. Rendered independently in both the compact card and the
 * "Show Larger" modal — matching the legacy dashlet, which instantiated
 * two separate DataTables (one per container) rather than sharing state
 * between them.
 */
export function DashletTable({ endpoint, columns }: { endpoint: string; columns: DashletColumn[] }) {
  const [rows, setRows] = useState<TableRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("desc");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const handle = window.setTimeout(() => {
      setSearch(searchInput.trim());
      setPage(1);
    }, 300);
    return () => window.clearTimeout(handle);
  }, [searchInput]);

  useEffect(() => {
    let isMounted = true;
    setLoading(true);

    dashboardTableService
      .fetch(endpoint, { page, per_page: PER_PAGE, search: search || undefined, sort, direction })
      .then((data) => {
        if (!isMounted) return;
        setRows(data.rows);
        setLastPage(data.meta.last_page);
        setTotal(data.meta.total);
        setError(null);
      })
      .catch(() => {
        if (isMounted) setError("Couldn't load this table. Please try again.");
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [endpoint, page, search, sort, direction]);

  const handleSort = (columnKey: string) => {
    if (sort === columnKey) {
      setDirection((prev) => (prev === "asc" ? "desc" : "asc"));
    } else {
      setSort(columnKey);
      setDirection("asc");
    }
    setPage(1);
  };

  return (
    <div className="flex flex-col gap-2">
      <input
        type="text"
        value={searchInput}
        onChange={(event) => setSearchInput(event.target.value)}
        placeholder="Search..."
        className="w-full rounded-md border border-slate-300 px-2 py-1 text-sm outline-none focus:border-slate-500"
      />

      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead>
            <tr className="border-b border-slate-200 bg-slate-50">
              {columns.map((column) => (
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
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => (
              <tr key={index} className="border-b border-slate-100">
                {columns.map((column) => (
                  <td key={column.key} className="px-2 py-1.5 text-slate-700">
                    {row[column.key]}
                  </td>
                ))}
              </tr>
            ))}
            {rows.length === 0 && !loading && !error && (
              <tr>
                <td colSpan={columns.length} className="px-2 py-4 text-center text-sm text-slate-400">
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

      <div className="flex items-center justify-between text-xs text-slate-500">
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
  );
}
