import type { PmsWorkPlanDetail } from "./pmsWorkPlan";
import { Modal } from "../../components/ui/Modal";

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs font-semibold text-slate-500">{label}</p>
      <p className="text-sm text-slate-800">{value || "—"}</p>
    </div>
  );
}

/** Ported from admin/pms_work_plan/view_workplan_ticket_v.php. Not ported: Inventory/Attachments panels — both are commented out of the legacy modal already. */
export function PmsWorkPlanViewModal({ adhoc, onClose }: { adhoc: PmsWorkPlanDetail; onClose: () => void }) {
  return (
    <Modal title={`Unplanned Maintenance Ticket — ${adhoc.ticket_no}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Vessel" value={adhoc.vessel} />
          <Field label="Department" value={adhoc.department ?? ""} />
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {adhoc.type === "EQUIPMENT" ? (
            <>
              <Field label="Component" value={adhoc.equipment_name ?? ""} />
              <Field label="Part" value={adhoc.part_name ?? ""} />
            </>
          ) : (
            <>
              <Field label="Location" value={adhoc.location ?? ""} />
              <Field label="Sub-Location" value={adhoc.sub_location ?? ""} />
            </>
          )}
        </div>
        <Field label="Activity" value={adhoc.activity_name} />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Job Class" value={adhoc.job_class ?? ""} />
          <Field label="Job Type" value={adhoc.job_type ?? ""} />
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="In-charge" value={adhoc.incharge} />
          <Field label="Assignee" value={adhoc.assignee ?? ""} />
        </div>
        <Field label="Work Procedure" value={adhoc.work_procedure ?? ""} />
        <Field label="Date of Activity" value={adhoc.date_of_activity} />
        <Field label="Details of Activity" value={adhoc.description ?? ""} />
        <Field label="Remarks" value={adhoc.remarks ?? ""} />

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
