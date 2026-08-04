import { useState } from "react";
import type { Dashlet, TableRow } from "./dashboard";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { DashletTable } from "./DashletTable";
import { dashboardTableService } from "./dashboardTableService";
import { NonconformityViewModal } from "../nonconformities/NonconformityViewModal";
import type { NonconformityDetail } from "../nonconformities/nonconformity";
import { DefectViewModal } from "../defects/DefectViewModal";
import type { DefectDetail } from "../defects/defects";

function DashletList({ items }: { items: Dashlet["items"] }) {
  if (items.length === 0) {
    return <p className="py-4 text-sm text-slate-400">No items.</p>;
  }

  return (
    <ul className="divide-y divide-slate-100">
      {items.map((item) => (
        <li key={item.label} className="flex flex-col gap-0.5 py-2">
          <span className="text-sm text-slate-800">{item.label}</span>
          <span className="text-xs text-slate-400">{item.meta}</span>
        </li>
      ))}
    </ul>
  );
}

/** The dashlet key + row column that legacy makes clickable to open a view modal (NCR NO. / SL NO.). */
const LINK_COLUMNS: Record<string, string> = {
  nonconformities: "ncr_no",
  defect_list: "sl_no",
};

export function DashletCard({ dashlet }: { dashlet: Dashlet }) {
  const [loaded, setLoaded] = useState(!dashlet.manual_load);
  const [loading, setLoading] = useState(false);
  const [showLarger, setShowLarger] = useState(false);
  const [nonconformity, setNonconformity] = useState<NonconformityDetail | null>(null);
  const [defect, setDefect] = useState<DefectDetail | null>(null);
  const isTable = dashlet.columns !== null && dashlet.endpoint !== null;
  const linkColumn = LINK_COLUMNS[dashlet.key];

  const handleLoad = () => {
    setLoading(true);
    // Legacy dashlets fetched this over AJAX per-panel; ours already has
    // the data, so this just preserves the original click-to-load UX.
    window.setTimeout(() => {
      setLoading(false);
      setLoaded(true);
    }, 400);
  };

  const handleLinkClick = (row: TableRow) => {
    const recordId = String(row.record_id);
    if (dashlet.key === "nonconformities") {
      dashboardTableService.fetchNonconformityDetail(recordId).then(setNonconformity);
    } else if (dashlet.key === "defect_list") {
      dashboardTableService.fetchDefectDetail(recordId).then(setDefect);
    }
  };

  return (
    <div
      className={`flex flex-col rounded-lg border border-slate-200 bg-white shadow-sm ${
        dashlet.span === "full" ? "lg:col-span-2" : ""
      }`}
    >
      <div className="flex items-center justify-between rounded-t-lg border-b border-sky-100 bg-sky-50 px-4 py-3">
        <span className="text-sm font-semibold text-slate-800">{dashlet.title}</span>
        <div className="flex items-center gap-2">
          {dashlet.extra_action === "add_task" && (
            <Button
              type="button"
              variant="success"
              className="!px-2 !py-1 text-xs"
              disabled
              title="Task module not yet migrated"
            >
              + Add Task
            </Button>
          )}
          {loaded && (
            <Button
              type="button"
              variant="info"
              className="!px-2 !py-1 text-xs"
              onClick={() => setShowLarger(true)}
            >
              Show Larger
            </Button>
          )}
        </div>
      </div>

      <div className={`${isTable ? "max-h-96" : "max-h-56"} overflow-y-auto px-4 py-2`}>
        {!loaded && !loading && (
          <div className="flex flex-col items-start gap-2 py-3">
            <p className="text-sm text-amber-700">Please click the load button.</p>
            <Button type="button" variant="secondary" className="!px-2 !py-1 text-xs" onClick={handleLoad}>
              Load
            </Button>
          </div>
        )}

        {loading && (
          <div className="flex items-center gap-2 py-3 text-sm text-amber-700">
            <span className="h-3 w-3 animate-spin rounded-full border-2 border-amber-300 border-t-amber-600" />
            Processing data...
          </div>
        )}

        {loaded &&
          !loading &&
          (isTable ? (
            <DashletTable
              endpoint={dashlet.endpoint!}
              columns={dashlet.columns!}
              defaultDirection={dashlet.key === "pms" ? "asc" : undefined}
              linkColumn={linkColumn}
              onLinkClick={linkColumn ? handleLinkClick : undefined}
            />
          ) : (
            <DashletList items={dashlet.items} />
          ))}
      </div>

      {showLarger && (
        <Modal title={dashlet.title} onClose={() => setShowLarger(false)}>
          {isTable ? (
            <DashletTable
              endpoint={dashlet.endpoint!}
              columns={dashlet.columns!}
              defaultDirection={dashlet.key === "pms" ? "asc" : undefined}
              linkColumn={linkColumn}
              onLinkClick={linkColumn ? handleLinkClick : undefined}
            />
          ) : (
            <DashletList items={dashlet.items} />
          )}
        </Modal>
      )}

      {nonconformity && <NonconformityViewModal nonconformity={nonconformity} onClose={() => setNonconformity(null)} />}
      {defect && <DefectViewModal defect={defect} onClose={() => setDefect(null)} />}
    </div>
  );
}
