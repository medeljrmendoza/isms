import { useState } from "react";
import type { Dashlet } from "./dashboard";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { DashletTable } from "./DashletTable";

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

export function DashletCard({ dashlet }: { dashlet: Dashlet }) {
  const [loaded, setLoaded] = useState(!dashlet.manual_load);
  const [loading, setLoading] = useState(false);
  const [showLarger, setShowLarger] = useState(false);
  const isTable = dashlet.columns !== null && dashlet.endpoint !== null;

  const handleLoad = () => {
    setLoading(true);
    // Legacy dashlets fetched this over AJAX per-panel; ours already has
    // the data, so this just preserves the original click-to-load UX.
    window.setTimeout(() => {
      setLoading(false);
      setLoaded(true);
    }, 400);
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
            <DashletTable endpoint={dashlet.endpoint!} columns={dashlet.columns!} />
          ) : (
            <DashletList items={dashlet.items} />
          ))}
      </div>

      {showLarger && (
        <Modal title={dashlet.title} onClose={() => setShowLarger(false)}>
          {isTable ? (
            <DashletTable endpoint={dashlet.endpoint!} columns={dashlet.columns!} />
          ) : (
            <DashletList items={dashlet.items} />
          )}
        </Modal>
      )}
    </div>
  );
}
