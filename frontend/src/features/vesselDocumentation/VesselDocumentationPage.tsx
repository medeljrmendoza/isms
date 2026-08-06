import { useEffect, useState } from "react";
import { vesselDocumentationService } from "./vesselDocumentationService";
import type { VesselDocumentationOption, VesselDocumentationRow } from "./vesselDocumentation";
import { Button } from "../../components/ui/Button";

const PER_PAGE = 10;

const MODULE_COLUMNS = [
  { key: "document_type", label: "TYPE", sortable: false },
  { key: "document", label: "DOCUMENT", sortable: false },
  { key: "doc_number", label: "CERT. NO.", sortable: true },
  { key: "issuing_body", label: "ISSUING BODY", sortable: true },
  { key: "date_issued", label: "ISSUED", sortable: true },
  { key: "date_expired", label: "EXPIRED", sortable: true },
  { key: "is_printer_friendly", label: "PF", sortable: false },
  { key: "warning_status", label: "WARNING", sortable: false },
  { key: "is_active", label: "STATUS", sortable: true },
];

function WarningIcon({ status }: { status: 0 | 1 | 2 }) {
  if (status === 2) return <span title="Expired" className="text-red-600">⚠</span>;
  if (status === 1) return <span title="Expiring soon" className="text-amber-500">⚠</span>;
  return <span className="text-slate-300">—</span>;
}

/**
 * Ported from admin/vessel_documentation/vessel_documentation_v.php.
 * Read-only: Add/Edit/Toggle Status/Delete have no legacy write-back
 * path — see VesselDocumentationRepository.
 */
export function VesselDocumentationPage() {
  const [vessels, setVessels] = useState<VesselDocumentationOption[]>([]);
  const [types, setTypes] = useState<VesselDocumentationOption[]>([]);
  const [vesselId, setVesselId] = useState("");
  const [typeId, setTypeId] = useState("");
  const [appliedVesselId, setAppliedVesselId] = useState<string | null>(null);
  const [appliedTypeId, setAppliedTypeId] = useState<string | null>(null);

  const [rows, setRows] = useState<VesselDocumentationRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("desc");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    vesselDocumentationService.options().then((data) => {
      setVessels(data.vessels);
    }).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (vesselId === "") {
      setTypes([]);
      return;
    }
    vesselDocumentationService.typeOptions(vesselId).then(setTypes).catch(() => undefined);
  }, [vesselId]);

  useEffect(() => {
    if (appliedVesselId === null) return;

    setLoading(true);
    vesselDocumentationService
      .list({
        vessel_id: appliedVesselId,
        type_id: appliedTypeId ?? undefined,
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
      .catch(() => setError("Couldn't load documents. Please try again."))
      .finally(() => setLoading(false));
  }, [appliedVesselId, appliedTypeId, page, sort, direction]);

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
    if (!vesselId) return;
    setAppliedVesselId(vesselId);
    setAppliedTypeId(typeId || null);
    setPage(1);
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">Vessel Documentation</h1>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Vessel</label>
            <select
              value={vesselId}
              onChange={(e) => {
                setVesselId(e.target.value);
                setTypeId("");
              }}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">Select vessel...</option>
              {vessels.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.label}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Type</label>
            <select
              value={typeId}
              onChange={(e) => setTypeId(e.target.value)}
              disabled={vesselId === ""}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm disabled:bg-slate-50"
            >
              <option value="">All types</option>
              {types.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.label}
                </option>
              ))}
            </select>
          </div>
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" disabled={!vesselId} onClick={applyFilter}>
            Filter
          </Button>
        </div>

        {appliedVesselId === null ? (
          <p className="px-4 py-8 text-center text-sm text-slate-400">Select a vessel and click Filter to view its documents.</p>
        ) : (
          <div className="overflow-x-auto px-4 py-3">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50">
                  {MODULE_COLUMNS.map((column) => (
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
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-slate-100">
                    <td className="px-2 py-1.5 text-slate-700">{row.document_type}</td>
                    <td className="px-2 py-1.5 text-slate-700">{row.document}</td>
                    <td className="px-2 py-1.5 text-slate-700">{row.doc_number ?? "—"}</td>
                    <td className="px-2 py-1.5 text-slate-700">{row.issuing_body ?? "—"}</td>
                    <td className="px-2 py-1.5 text-slate-700">{row.date_issued ?? "—"}</td>
                    <td className="px-2 py-1.5 text-slate-700">{row.date_expired ?? "—"}</td>
                    <td className="px-2 py-1.5 text-center">{row.is_printer_friendly ? "✓" : ""}</td>
                    <td className="px-2 py-1.5 text-center">
                      <WarningIcon status={row.warning_status} />
                    </td>
                    <td className="px-2 py-1.5 text-center">
                      <span className={row.is_active ? "font-semibold text-emerald-600" : "font-semibold text-red-500"}>
                        {row.is_active ? "Active" : "Inactive"}
                      </span>
                    </td>
                  </tr>
                ))}
                {rows.length === 0 && !loading && !error && (
                  <tr>
                    <td colSpan={MODULE_COLUMNS.length} className="px-2 py-6 text-center text-sm text-slate-400">
                      No documents.
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
        )}
      </div>
    </div>
  );
}
