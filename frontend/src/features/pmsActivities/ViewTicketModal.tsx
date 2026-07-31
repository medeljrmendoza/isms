import type { PmsTicketDetail } from "./pmsActivities";
import { Modal } from "../../components/ui/Modal";

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs font-semibold text-slate-500">{label}</p>
      <p className="text-sm text-slate-800">{value || "—"}</p>
    </div>
  );
}

/**
 * Ported from view_pms_ticket_reports.php — legacy's endpoint that
 * populates this modal isn't present in the given controller, so this
 * is built directly from the tb_pms_ticket fields captured by
 * update_last_done()/postpone_activity().
 */
export function ViewTicketModal({ ticket, onClose }: { ticket: PmsTicketDetail; onClose: () => void }) {
  return (
    <Modal title={`Ticket — ${ticket.ticket_no}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Vessel" value={ticket.vessel} />
          <Field label="Type" value={ticket.type} />
        </div>
        <Field label="Activity" value={ticket.activity_name} />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Component" value={ticket.equipment_name ?? ""} />
          <Field label="Part" value={ticket.part_name ?? ""} />
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Date of Activity" value={ticket.date_of_activity} />
          <Field label="Frequency" value={ticket.frequency ?? ""} />
        </div>
        {ticket.type !== "POSTPONED" ? (
          <Field label="Overdue?" value={ticket.is_overdue ? "Yes" : "No"} />
        ) : null}
        <Field label="Description" value={ticket.description ?? ""} />
        <Field label="Possible Cause" value={ticket.possible_cause ?? ""} />
        <Field label="Remarks" value={ticket.remarks ?? ""} />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Previous Last Done" value={ticket.previous_last_done ?? ""} />
          <Field label="Previous Due Date" value={ticket.previous_due_date ?? ""} />
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Reported By" value={ticket.reported_by ?? ""} />
          <Field label="Created" value={ticket.created_at} />
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
