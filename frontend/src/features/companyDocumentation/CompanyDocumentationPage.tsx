import { useEffect, useState } from "react";
import { companyDocumentationService } from "./companyDocumentationService";
import type { CompanyDocumentationDetail, CompanyDocumentationOption, CompanyDocumentationRow } from "./companyDocumentation";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { CompanyDocumentationForm } from "./CompanyDocumentationForm";

const PER_PAGE = 10;

const MODULE_COLUMNS = [
  { key: "document_type", label: "TYPE", sortable: false },
  { key: "document", label: "DOCUMENT", sortable: false },
  { key: "doc_number", label: "DOCUMENT NO.", sortable: true },
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

/** Ported from admin/company_documentation/company_documentation_v.php. */
export function CompanyDocumentationPage() {
  const [types, setTypes] = useState<CompanyDocumentationOption[]>([]);
  const [typeId, setTypeId] = useState("");
  const [appliedTypeId, setAppliedTypeId] = useState<string | null>(null);

  const [rows, setRows] = useState<CompanyDocumentationRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [sort, setSort] = useState<string | undefined>(undefined);
  const [direction, setDirection] = useState<"asc" | "desc">("desc");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<CompanyDocumentationDetail | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [canCreateRecord, setCanCreateRecord] = useState(false);

  useEffect(() => {
    companyDocumentationService.typeOptions().then((data) => {
      setTypes(data.types);
      setCanCreateRecord(data.can_create_record);
    }).catch(() => undefined);
  }, []);

  useEffect(() => {
    setLoading(true);
    companyDocumentationService
      .list({
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
  }, [appliedTypeId, page, sort, direction, reloadKey]);

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
    setAppliedTypeId(typeId || null);
    setPage(1);
  };

  const resetFilter = () => {
    setTypeId("");
    setAppliedTypeId(null);
    setPage(1);
  };

  const reload = () => setReloadKey((k) => k + 1);

  const openEdit = async (id: number | string) => {
    setActionError(null);
    const detail = await companyDocumentationService.show(id);
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
          <h1 className="text-base font-semibold text-slate-800">Company Documentation</h1>
          {canCreateRecord && (
            <Button
              type="button"
              variant="success"
              className="!px-3 !py-1.5 text-sm"
              onClick={() => {
                setEditing(null);
                setFormOpen(true);
              }}
            >
              + Add Document
            </Button>
          )}
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Type</label>
            <select
              value={typeId}
              onChange={(e) => setTypeId(e.target.value)}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">All types</option>
              {types.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.label}
                </option>
              ))}
            </select>
          </div>
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={applyFilter}>
            Filter
          </Button>
          <Button type="button" variant="info" className="!px-3 !py-1.5 text-sm" onClick={resetFilter}>
            View All
          </Button>
        </div>

        {actionError && <p className="px-4 pt-2 text-sm text-red-600">{actionError}</p>}

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
                <th className="px-2 py-1.5 font-semibold text-slate-600">ACTIONS</th>
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
                  <td className="px-2 py-1.5">
                    <div className="flex flex-wrap gap-1">
                      {row.can_edit && (
                        <>
                          <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => openEdit(row.id)}>
                            Edit
                          </Button>
                          <Button
                            type="button"
                            variant={row.is_active ? "success" : "secondary"}
                            className="!px-1.5 !py-0.5 text-xs"
                            onClick={() => runAction(() => companyDocumentationService.toggleStatus(row.id as number))}
                          >
                            {row.is_active ? "Inactivate" : "Activate"}
                          </Button>
                        </>
                      )}
                      {row.can_delete && (
                        <Button
                          type="button"
                          variant="secondary"
                          className="!px-1.5 !py-0.5 text-xs text-red-600"
                          onClick={() => {
                            if (window.confirm(`Delete this document (${row.document})?`)) {
                              runAction(() => companyDocumentationService.destroy(row.id as number));
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
                  <td colSpan={MODULE_COLUMNS.length + 1} className="px-2 py-6 text-center text-sm text-slate-400">
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
      </div>

      {formOpen && (
        <Modal title={editing ? `Edit Document — ${editing.document}` : "Add Company Document"} onClose={() => setFormOpen(false)}>
          <CompanyDocumentationForm
            record={editing ?? undefined}
            onCancel={() => setFormOpen(false)}
            onSuccess={() => {
              setFormOpen(false);
              reload();
            }}
          />
        </Modal>
      )}
    </div>
  );
}
