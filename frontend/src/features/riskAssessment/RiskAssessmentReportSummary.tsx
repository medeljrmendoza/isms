import type { RiskAssessmentDetail } from "./riskAssessment";

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

const RISK_BADGE: Record<string, string> = {
  LOW: "bg-sky-100 text-sky-700",
  MID: "bg-amber-100 text-amber-700",
  HIGH: "bg-red-100 text-red-700",
};

function RiskBadge({ value }: { value: string | null }) {
  if (!value) return <span className="text-slate-400">—</span>;
  return <span className={`rounded px-1.5 py-0.5 text-xs font-semibold ${RISK_BADGE[value] ?? "bg-slate-100 text-slate-600"}`}>{value}</span>;
}

/**
 * Read-only header + assessment table + personnel, shared by the view
 * modal and the approval form — ported from view_risk_assessment_modal.php
 * / add_risk_assessment_v.php's disabled report fields, which show the
 * exact same information in both places.
 */
export function RiskAssessmentReportSummary({ r }: { r: RiskAssessmentDetail }) {
  return (
    <div className="flex flex-col gap-3">
      <Section title="Report">
        <Row label="Report No." value={r.report_no} />
        <Row label="Vessel" value={r.vessel} />
        <Row label="Activity" value={r.activity} />
        <Row label="Assessment Date" value={r.risk_date} />
        <Row label="Schedule of Task" value={r.risk_schedule} />
        <Row label="Port" value={r.port} />
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
                  <th className="px-1.5 py-1 text-center font-semibold text-slate-600">S</th>
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
                      <RiskBadge value={h.risk} />
                    </td>
                    <td className="px-1.5 py-1 text-slate-700">{h.existing_control ?? "—"}</td>
                    <td className="px-1.5 py-1 text-slate-700">{h.additional_control ?? "—"}</td>
                    <td className="px-1.5 py-1 text-center text-slate-700">{h.re_severity ?? "—"}</td>
                    <td className="px-1.5 py-1 text-center text-slate-700">{h.re_likelihood ?? "—"}</td>
                    <td className="px-1.5 py-1 text-center">
                      <RiskBadge value={h.re_risk} />
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

      <Section title="To Be Filled Out By Vessel">
        <Row label="Master" value={r.master} />
        <Row label="CE/CO" value={r.ce_co} />
        <Row label="Remarks" value={r.vessel_remarks} />
      </Section>
    </div>
  );
}
