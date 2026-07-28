import type { NonSireReportDetail } from "./nonSire";
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

/** Ported from admin/nonsire/view_non_sire.php. */
export function NonSireReportViewModal({ nonSireReport: r, onClose }: { nonSireReport: NonSireReportDetail; onClose: () => void }) {
  return (
    <Modal title={`Non-SIRE — ${r.vessel}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Section title="Header">
          <Row label="Vessel" value={r.vessel} />
          <Row label="Added By" value={r.added_by} />
          <Row label="Date of Inspection" value={r.dateof_inspection} />
          <Row label="Place of Inspection" value={r.placeof_inspection} />
          <Row label="Company" value={r.company_name} />
          <Row label="Inspector" value={r.inspector_name} />
          <Row label="Inspection Type" value={r.inspection_type} />
          <Row label="Pass/Fail" value={r.pass_fail} />
          <Row label="Cost" value={r.sire_cost} />
        </Section>

        <Section title="Status">
          <Row label="Published" value={flagLabel(r.published)} />
          <Row label="Approved" value={flagLabel(r.is_approved)} />
        </Section>

        <Section title="Remarks">
          <Row label="Vessel Remarks" value={r.vessel_remarks} />
          <Row label="Shore Remarks" value={r.shore_remarks} />
        </Section>
      </div>
    </Modal>
  );
}
