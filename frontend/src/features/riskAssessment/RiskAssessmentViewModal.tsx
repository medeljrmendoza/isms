import type { RiskAssessmentDetail } from "./riskAssessment";
import { Modal } from "../../components/ui/Modal";
import { RiskAssessmentReportSummary } from "./RiskAssessmentReportSummary";

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

/** Ported from view_risk_assessment_modal.php. */
export function RiskAssessmentViewModal({ report: r, onClose }: { report: RiskAssessmentDetail; onClose: () => void }) {
  return (
    <Modal title={`Risk Assessment Report — ${r.report_no}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <RiskAssessmentReportSummary r={r} />

        {r.approval_from_shore && (
          <Section title="To Be Filled Out By Technical Superintendent">
            <Row label="Approved?" value={r.shore_is_approved ? "YES" : "NO"} />
            <Row label="Date Approved" value={r.date_approved} />
            <Row label="Remarks" value={r.shore_remarks} />
          </Section>
        )}

        {r.approval_from_marine && (
          <Section title="To Be Filled Out By Marine Superintendent">
            <Row label="Approved?" value={r.marine_is_approved ? "YES" : "NO"} />
            <Row label="Date Approved" value={r.marine_date_approved} />
            <Row label="Remarks" value={r.marine_remarks} />
          </Section>
        )}
      </div>
    </Modal>
  );
}
