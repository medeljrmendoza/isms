import type { IncidentReportDetail } from "../../types/incidentReport";
import { Modal } from "../ui/Modal";

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

const ATMOSPHERIC_LABELS: Record<string, string> = {
  atmospheric_clear: "Clear",
  atmospheric_partly_cloudy: "Partly Cloudy",
  atmospheric_overcast: "Overcast",
  atmospheric_fog: "Fog",
  atmospheric_rain: "Rain",
  atmospheric_snow: "Snow",
};

const EVIDENCE_LABELS: Record<string, string> = {
  evidence_safety_meeting: "Safety Meeting",
  evidence_certificate: "Certificate / Record of Training",
  evidence_logbook: "Logbook Entry",
  evidence_delivery: "Delivery Note",
  evidence_photo: "Photo",
  evidence_company: "Company Forms",
};

/** Ported from admin/incident/view_incident_report_dialog.php. */
export function IncidentReportViewModal({ incidentReport: r, onClose }: { incidentReport: IncidentReportDetail; onClose: () => void }) {
  const isAccident = r.nature_type === "accident";

  const typeLabel = isAccident
    ? r.nature_of_incident_label === "Other"
      ? `Other — ${r.others}`
      : r.nature_of_incident_label === "Collision"
        ? `Collision — ${r.accident_collision}`
        : r.nature_of_incident_label
    : { unsafe_act: "Unsafe Act", unsafe_condition: "Unsafe Condition", near_miss: "Near Miss" }[r.hazardous_occurrence_type ?? ""];

  const atmospheric = Object.entries(ATMOSPHERIC_LABELS)
    .filter(([key]) => r[key as keyof typeof ATMOSPHERIC_LABELS])
    .map(([, label]) => label);
  if (r.atmospheric_other) atmospheric.push(`Other — ${r.atmospheric_other_name ?? ""}`.trim());

  const evidence = Object.entries(EVIDENCE_LABELS)
    .filter(([key]) => r[key as keyof typeof EVIDENCE_LABELS])
    .map(([, label]) => label);
  if (r.evidence_others) evidence.push(`Others — ${r.evidence_others_name ?? ""}`.trim());

  return (
    <Modal title={`Incident Report — ${r.vessel}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Section title="Header">
          <Row label="Vessel" value={r.vessel} />
          <Row label="Voyage No." value={r.voyage_no} />
          <Row label="Date of Report" value={r.dateof_report} />
          <Row label="Report No." value={r.report_no} />
          <Row label="Master" value={r.master_name} />
          <Row label="Chief Engineer" value={r.chief_engineer_name} />
          <Row label="Person Reporting" value={r.person_reporting} />
        </Section>

        <Section title="Nature">
          <Row label="Nature" value={r.nature} />
          <Row label="Type" value={typeLabel} />
        </Section>

        <Section title="Statement of Facts">
          <p className="whitespace-pre-wrap text-sm text-slate-800">{r.statementof_work || "—"}</p>
        </Section>

        {isAccident && (
          <Section title="Particulars of Accident">
            <Row label="BAC Tested / VDR Saved" value={`${r.bac ?? "—"} / ${r.vdr ?? "—"}`} />
            <Row label="Date/Time of Event" value={`${r.dateof_event ?? "—"} ${r.timeof_event ?? ""}`} />
            <Row label="Zone / Country" value={`${r.zone ?? "—"} / ${r.country ?? "—"}`} />
            <Row label="Speed / Course" value={`${r.speed ?? "—"} / ${r.course ?? "—"}`} />
            <Row label="Draft Fwd / Aft" value={`${r.draft_forward ?? "—"} / ${r.draft_alt ?? "—"}`} />
            <Row label="Wind / Sea / Swell Direction" value={`${r.wind_direction ?? "—"} / ${r.direction_sea ?? "—"} / ${r.direction_swell ?? "—"}`} />
            <Row label="Geographical Location" value={r.geographical_location} />
            <Row label="Port of Departure / Date" value={`${r.port_departure ?? "—"} / ${r.date_departure ?? "—"}`} />
            <Row label="Port to Which Bound" value={r.port_which_bound} />
            <Row label="Cargo Type / Quantity" value={`${r.type_cargo ?? "—"} / ${r.cargo_quantity ?? "—"}`} />
            <Row label="Special Requirement" value={r.special_requirement} />
            <Row label="Atmospheric Conditions" value={atmospheric.length > 0 ? atmospheric.join(", ") : "—"} />
            <Row
              label="Distance of Visibility"
              value={[r.distance1 && "Under 3 Miles", r.distance2 && "2-5 Miles", r.distance3 && "Over 5 Miles"].filter(Boolean).join(", ") || "—"}
            />
            <Row label="Sea" value={[r.sea1 && "Smooth to Slight", r.sea2 && "Moderate to Rough", r.sea3 && "High"].filter(Boolean).join(", ") || "—"} />
            <Row label="Reported to Flag State / RO" value={r.fs_ro} />
          </Section>
        )}

        {!isAccident && (
          <Section title="Location / Operation">
            <Row label="Location" value={r.incident_location_label === "OTHER" ? `OTHER — ${r.location_other}` : r.incident_location_label} />
            <Row label="Ship's Position" value={r.ship_position} />
            <Row
              label="Ship's Operation"
              value={r.incident_operation_label === "OTHER" ? `OTHER — ${r.ship_operation_other}` : r.incident_operation_label}
            />
            <Row label="PPE Used" value={r.hazardous_occurrence_ppe_used} />
            <Row label="PPE Remarks" value={r.hazardous_occurrence_ppe_used_comment} />
            <Row label="Severity" value={r.hazardous_occurrence_severity} />
            <Row label="Severity Remarks" value={r.hazardous_occurrence_severity_comment} />
            <Row label="Likelihood" value={r.hazardous_occurrence_likelihood} />
            <Row label="Likelihood Remarks" value={r.hazardous_occurrence_likelihood_comment} />
          </Section>
        )}

        <Section title="Injury to People">
          <Row label="Severity" value={r.severity_itp ?? "No injury reported"} />
          {r.severity_itp && (
            <>
              <Row
                label="Type of Injury"
                value={r.type_of_injury_label === "Other" ? `Other — ${r.other_typeof_injury}` : r.type_of_injury_label}
              />
              <Row
                label="Affected Area"
                value={r.location_of_injury_label === "Other" ? `Other — ${r.other_affected_area}` : r.location_of_injury_label}
              />
              <Row label="Remarks" value={r.comment_itp} />
            </>
          )}
        </Section>

        <Section title="Damage to Vessel / Port Facility">
          <Row label="Severity" value={r.severity_itv ?? "No damage reported"} />
          {r.severity_itv && <Row label="Remarks" value={r.comment_itv} />}
        </Section>

        <Section title="Root Cause / Investigation / Analysis / Corrective Action">
          {r.root_causes.length === 0 && <p className="text-sm text-slate-400">None recorded.</p>}
          {r.root_causes.map((rc, index) => (
            <div key={rc.id ?? index} className="border-b border-slate-100 py-2 last:border-0">
              <p className="text-sm font-semibold text-slate-700">
                {rc.root_cause_category_label === "OTHER" ? `OTHER — ${rc.root_cause_other}` : `${rc.root_cause_category_label}: ${rc.root_cause_label}`}
              </p>
              <Row label="Investigation" value={rc.investigation} />
              <Row label="Analysis" value={rc.analysis} />
              <Row label="Corrective Actions" value={rc.corrective_actions} />
            </div>
          ))}
        </Section>

        <Section title="Participants">
          {r.persons.length === 0 && <p className="text-sm text-slate-400">None recorded.</p>}
          {r.persons.map((p, index) => (
            <Row key={p.id ?? index} label={p.position ?? "—"} value={p.person_name} />
          ))}
          {isAccident && (
            <div className="mt-2 grid grid-cols-4 gap-2 border-t border-slate-100 pt-2 text-xs">
              <span className="font-semibold text-slate-600">Onboard: Crew {r.crew_onboard ?? 0} / Other {r.other_onboard ?? 0} / Total {r.total_onboard ?? 0}</span>
              <span className="font-semibold text-slate-600">Dead: Crew {r.crew_dead ?? 0} / Other {r.other_dead ?? 0} / Total {r.total_dead ?? 0}</span>
              <span className="font-semibold text-slate-600">Missing: Crew {r.crew_missing ?? 0} / Other {r.other_missing ?? 0} / Total {r.total_missing ?? 0}</span>
              <span className="font-semibold text-slate-600">Injured: Crew {r.crew_injured ?? 0} / Other {r.other_injured ?? 0} / Total {r.total_injured ?? 0}</span>
            </div>
          )}
        </Section>

        <Section title="Signature">
          <Row label="Signed By" value={r.signed_by} />
          <Row label="Date Signed" value={r.date_signed} />
          <Row label="Vessel Remarks" value={r.vessel_remarks} />
        </Section>

        {!isAccident && (
          <Section title="For Office Use Only">
            <Row label="Subject for Investigation?" value={r.subject_investigation} />
            <Row label="Required Evidence" value={evidence.length > 0 ? evidence.join(", ") : "—"} />
            <Row label="Causal Factor" value={r.causal_factor} />
            <Row label="Intermediate Cause" value={r.intermediate_cause} />
            <Row label="Root Cause" value={r.shore_root_cause_summary} />
          </Section>
        )}

        <Section title="Review">
          <Row label="Date Received" value={r.date_received} />
          <Row label="Reviewed By" value={r.reviewed_by} />
          <Row label="Investigator" value={r.investigator} />
          <Row label="DPA" value={r.dpa} />
          <Row label="Closing Date" value={r.closing_date} />
        </Section>
      </div>
    </Modal>
  );
}
