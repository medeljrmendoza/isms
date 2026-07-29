import type { DrillReportDetail } from "./drill";
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

/** Ported from admin/drillreports/view_drill_report.php. */
export function DrillReportViewModal({ drillReport: r, onClose }: { drillReport: DrillReportDetail; onClose: () => void }) {
  return (
    <Modal title={`Drill Report — ${r.vessel}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Section title="Header">
          <Row label="Vessel" value={r.vessel} />
          <Row label="Type" value={r.drill_type} />
          <Row label="Drill" value={r.drill_name} />
          <Row label="Master" value={r.master_name} />
          <Row label="Date" value={r.drill_date} />
          <Row label="Time" value={r.drill_time_from} />
          <Row label="Position" value={r.drill_position} />
        </Section>

        <Section title="Ranks of Crew Participated">
          <Row label="Crew" value={r.crew.map((c) => c.crew_name).join(", ")} />
        </Section>

        <Section title="Details of Drill / Training">
          <Row label="Details" value={r.drill_details} />
        </Section>

        <Section title="Found Deficiencies">
          <Row label="Deficiencies" value={r.drill_deficiencies} />
        </Section>

        <Section title="Master's Option for Improvement and Corrective Action">
          <Row label="Corrective Action" value={r.drill_corrective_action} />
        </Section>

        <Section title="Remarks">
          <Row label="Report Date" value={r.report_date} />
          <Row label="Vessel Remarks" value={r.vessel_remarks} />
          <Row label="Received Date" value={r.receipt_date} />
          <Row label="Shore Remarks" value={r.shore_remarks} />
        </Section>
      </div>
    </Modal>
  );
}
