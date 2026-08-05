import { useEffect, useState } from "react";
import { pmsConfigurationService } from "./pmsConfigurationService";
import type { PmsConfigurationOption, PmsConfigurationRow } from "./pmsConfiguration";
import { Button } from "../../components/ui/Button";
import { Modal } from "../../components/ui/Modal";
import { PmsConfigurationForm } from "./PmsConfigurationForm";

const PER_PAGE = 10;

/** Ported from admin/pms_setup_configuration/configuration.php. */
export function PmsConfigurationPage() {
  const [principals, setPrincipals] = useState<PmsConfigurationOption[]>([]);
  const [principalId, setPrincipalId] = useState("");
  const [appliedPrincipalId, setAppliedPrincipalId] = useState<string | null>(null);

  const [rows, setRows] = useState<PmsConfigurationRow[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [editing, setEditing] = useState<PmsConfigurationRow | null>(null);

  useEffect(() => {
    pmsConfigurationService.options().then((data) => setPrincipals(data.principals)).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (appliedPrincipalId === null) return;

    setLoading(true);
    pmsConfigurationService
      .list({ principal_id: appliedPrincipalId, page, per_page: PER_PAGE })
      .then((data) => {
        setRows(data.rows);
        setLastPage(data.meta.last_page);
        setTotal(data.meta.total);
        setError(null);
      })
      .catch(() => setError("Couldn't load vessels. Please try again."))
      .finally(() => setLoading(false));
  }, [appliedPrincipalId, page]);

  const applyFilter = () => {
    if (!principalId) return;
    setAppliedPrincipalId(principalId);
    setPage(1);
  };

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">PMS Configuration</h1>
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Principal</label>
            <select
              value={principalId}
              onChange={(e) => setPrincipalId(e.target.value)}
              className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">Select principal...</option>
              {principals.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.label}
                </option>
              ))}
            </select>
          </div>
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" disabled={!principalId} onClick={applyFilter}>
            Filter
          </Button>
        </div>

        {appliedPrincipalId === null ? (
          <p className="px-4 py-8 text-center text-sm text-slate-400">Select a principal and click Filter to view its vessels.</p>
        ) : (
          <div className="overflow-x-auto px-4 py-3">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50">
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">VESSEL</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">SHORT NAME</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">CONFIGURATION</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">ACTION</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-slate-100">
                    <td className="px-2 py-1.5 text-slate-700">{row.vessel_name}</td>
                    <td className="px-2 py-1.5 text-slate-700">{row.short_name ?? "—"}</td>
                    <td className="px-2 py-1.5 text-slate-700">{row.configuration ?? "—"}</td>
                    <td className="px-2 py-1.5">
                      <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => setEditing(row)}>
                        Edit
                      </Button>
                    </td>
                  </tr>
                ))}
                {rows.length === 0 && !loading && !error && (
                  <tr>
                    <td colSpan={4} className="px-2 py-6 text-center text-sm text-slate-400">
                      No vessels for this principal.
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
                <Button type="button" variant="secondary" className="!px-2 !py-0.5 text-xs" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
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
                  onClick={() => setPage((p) => p + 1)}
                >
                  Next
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>

      {editing && (
        <Modal title={`Edit Configuration — ${editing.vessel_name}`} onClose={() => setEditing(null)}>
          <PmsConfigurationForm
            record={editing}
            onCancel={() => setEditing(null)}
            onSuccess={(updated) => {
              setRows((prev) => prev.map((r) => (r.id === updated.id ? updated : r)));
              setEditing(null);
            }}
          />
        </Modal>
      )}
    </div>
  );
}
