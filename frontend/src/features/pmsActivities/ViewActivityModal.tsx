import type { PmsActivityDetail } from "./pmsActivities";
import { Modal } from "../../components/ui/Modal";

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs font-semibold text-slate-500">{label}</p>
      <p className="text-sm text-slate-800">{value || "—"}</p>
    </div>
  );
}

/** Ported from view_activity(). */
export function ViewActivityModal({ activity, onClose }: { activity: PmsActivityDetail; onClose: () => void }) {
  return (
    <Modal title={`Activity — ${activity.activity_code ?? activity.activity_name}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Vessel" value={activity.vessel} />
          <Field label="In-charge" value={activity.incharge ?? ""} />
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Component" value={activity.equipment_name ?? ""} />
          <Field label="Part" value={activity.part_name ?? ""} />
        </div>
        <Field label="Activity" value={activity.activity_name} />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Field label="Department" value={activity.department ?? ""} />
          <Field label="Group" value={activity.main_group ?? ""} />
          <Field label="Criticality" value={activity.criticality ?? ""} />
        </div>
        <Field label="Work Procedure" value={activity.work_procedure ?? ""} />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Frequency" value={activity.frequency ?? ""} />
          <Field label="Last Activity" value={activity.last_done ?? ""} />
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Due Date" value={activity.is_running_hours_tracked ? "Tracked by Running Hours" : (activity.due_date ?? "")} />
          <Field label="Status" value={activity.is_postponed ? "Postponed" : activity.is_overdue ? "Overdue" : ""} />
        </div>

        <div className="flex justify-end border-t border-slate-100 pt-4">
          <button
            type="button"
            onClick={onClose}
            className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
          >
            Close
          </button>
        </div>
      </div>
    </Modal>
  );
}
