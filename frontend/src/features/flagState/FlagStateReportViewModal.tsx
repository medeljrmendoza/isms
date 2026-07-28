import type { FlagStateReportDetail } from "./flagState";
import { Modal } from "../../components/ui/Modal";

function Row({ label, value }: { label: string; value: string | number | null | undefined }) {
  return (
    <div className="grid grid-cols-3 gap-2 border-b border-slate-100 py-1.5 text-sm last:border-0">
      <span className="font-semibold text-slate-600">{label}</span>
      <span className="col-span-2 text-slate-800">{value === null || value === undefined || value === "" ? "—" : value}</span>
    </div>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-md border border-slate-200">
      <div className="border-b border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-semibold text-slate-700">{title}</div>
      <div className="px-3 py-2">{children}</div>
    </div>
  );
}

function flagLabel(value: boolean | null): string {
  if (value === null) return "—";
  return value ? "Yes" : "No";
}

/** Ported from admin/flagstate/view_flag_state.php. */
export function FlagStateReportViewModal({ flagStateReport: r, onClose }: { flagStateReport: FlagStateReportDetail; onClose: () => void }) {
  return (
    <Modal title={`Flag State — ${r.vessel}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Section title="Header">
          <Row label="Ref. No." value={r.ref_no} />
          <Row label="Vessel" value={r.vessel} />
          <Row label="Added By" value={r.added_by} />
          <Row label="Date of Inspection" value={r.dateof_inspection} />
          <Row label="Place of Inspection" value={r.placeof_inspection} />
          <Row label="Inspector" value={r.inspector} />
          <Row label="Cost" value={r.flag_cost} />
        </Section>

        <Section title="Status">
          <Row label="Published" value={flagLabel(r.published)} />
          <Row label="Approved" value={flagLabel(r.is_approved)} />
        </Section>

        <Section title="Non-conformities">
          <Row label="Pending / Total" value={`${r.pending_nc_count} / ${r.total_nc_count}`} />
        </Section>

        <Section title="Remarks">
          <Row label="Vessel Remarks" value={r.vessel_remarks} />
          <Row label="Shore Remarks" value={r.shore_remarks} />
        </Section>
      </div>
    </Modal>
  );
}
