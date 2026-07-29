import type { RiskAssessmentShoreDetail } from "./riskAssessmentShore";
import { Modal } from "../../components/ui/Modal";
import { riskBadgeClass } from "./riskCalc";

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

/** Ported from view_risk_assessment_modal.php (Shore). */
export function RiskAssessmentShoreViewModal({ report: r, onClose }: { report: RiskAssessmentShoreDetail; onClose: () => void }) {
  return (
    <Modal title={`Risk Assessment Report (Shore) — ${r.report_no}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Section title="Report">
          <Row label="Type" value={r.report_type} />
          {r.report_type === "VESSEL" && <Row label="Vessel" value={r.vessel} />}
          <Row label="Activity" value={r.activity} />
          <Row label="Assessment Date" value={r.risk_date} />
          <Row label="Schedule of Task" value={r.risk_schedule} />
          <Row label={r.report_type === "SHORE" ? "Location" : "Port"} value={r.port} />
          <Row label="Department" value={r.department} />
          <Row label="Category" value={r.category} />
          <Row label="Task" value={r.task} />
          <Row label="Overall Risk" value={r.overall_risk} />
        </Section>

        {r.hazards.length > 0 && (
          <Section title="Assessment Table">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-slate-200">
                    <th className="px-1.5 py-1 font-semibold text-slate-600">#</th>
                    <th className="px-1.5 py-1 font-semibold text-slate-600">Unwanted Consequences</th>
                    <th className="px-1.5 py-1 font-semibold text-slate-600">Underlying Causes / Hazards</th>
                    <th className="px-1.5 py-1 text-center font-semibold text-slate-600">S</th>
                    <th className="px-1.5 py-1 text-center font-semibold text-slate-600">L</th>
                    <th className="px-1.5 py-1 text-center font-semibold text-slate-600">Risk</th>
                    <th className="px-1.5 py-1 font-semibold text-slate-600">Existing Controls</th>
                    <th className="px-1.5 py-1 font-semibold text-slate-600">Additional Controls</th>
                    <th className="px-1.5 py-1 text-center font-semibold text-slate-600">L</th>
                    <th className="px-1.5 py-1 text-center font-semibold text-slate-600">Final Risk</th>
                  </tr>
                </thead>
                <tbody>
                  {r.hazards.map((h) => (
                    <tr key={h.id} className="border-b border-slate-100 align-top">
                      <td className="px-1.5 py-1 text-slate-700">{h.arrangement}</td>
                      <td className="px-1.5 py-1 text-slate-700">{h.unwanted_consequences ?? "—"}</td>
                      <td className="px-1.5 py-1 text-slate-700">{h.underlying_causes ?? "—"}</td>
                      <td className="px-1.5 py-1 text-center text-slate-700">{h.severity ?? "—"}</td>
                      <td className="px-1.5 py-1 text-center text-slate-700">{h.likelihood ?? "—"}</td>
                      <td className="px-1.5 py-1 text-center">
                        <span className={`rounded px-1.5 py-0.5 text-xs font-semibold ${riskBadgeClass(h.risk)}`}>{h.risk ?? "—"}</span>
                      </td>
                      <td className="px-1.5 py-1 text-slate-700">{h.existing_control ?? "—"}</td>
                      <td className="px-1.5 py-1 text-slate-700">{h.additional_control ?? "—"}</td>
                      <td className="px-1.5 py-1 text-center text-slate-700">{h.re_likelihood ?? "—"}</td>
                      <td className="px-1.5 py-1 text-center">
                        <span className={`rounded px-1.5 py-0.5 text-xs font-semibold ${riskBadgeClass(h.re_risk)}`}>{h.re_risk ?? "—"}</span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Section>
        )}

        {r.people.length > 0 && (
          <Section title="Personnel Involved">
            {r.people.map((p) => (
              <Row key={p.id} label={`#${p.arrangement}`} value={p.person_details} />
            ))}
          </Section>
        )}

        <Row label="Report needs approval from Technical Superintendent?" value={r.approval_from_shore ? "YES" : "NO"} />
        <Row label="Report needs approval from Marine Superintendent?" value={r.approval_from_marine ? "YES" : "NO"} />

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

        <Section title="Remarks">
          <Row label="Remarks" value={r.remarks} />
          <Row label="Date Closed" value={r.date_closed} />
        </Section>
      </div>
    </Modal>
  );
}
