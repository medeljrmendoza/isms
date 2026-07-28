import type { NonconformityDetail } from "./nonconformity";
import { Modal } from "../../components/ui/Modal";

function Row({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div className="grid grid-cols-3 gap-2 border-b border-slate-100 py-1.5 text-sm last:border-0">
      <span className="font-semibold text-slate-600">{label}</span>
      <span className="col-span-2 text-slate-800">{value || "—"}</span>
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

const ATTACHMENT_LABELS: Record<string, string> = {
  attach_safety_meeting: "Safety Meeting",
  attach_record_training: "Record of Training",
  attach_logbook: "Logbook Entry",
  attach_delivery_note: "Delivery Note",
  attach_photo: "Photo",
  attach_company_forms: "Company Forms",
};

/** Ported from admin/nonconformities/view_nonconformity.php, minus the file-attachment list (no upload pipeline — see NonconformityController docblock) and print button (browser print covers that). */
export function NonconformityViewModal({ nonconformity, onClose }: { nonconformity: NonconformityDetail; onClose: () => void }) {
  const verificationLabel = {
    COMPLETED: "Completed per SMS",
    "FOLLOW-UP": `Follow-up is required as per SMS${nonconformity.verification_followup ? ` — ${nonconformity.verification_followup}` : ""}`,
    ASSISTANCE: `Assistance is required${nonconformity.verification_assistance ? ` — ${nonconformity.verification_assistance}` : ""}`,
  }[nonconformity.verification ?? ""];

  const closeOutParts = [
    nonconformity.close_out_completed && "Completed and closed out",
    nonconformity.close_out_followup &&
      `Follow-up is required as per SMS${nonconformity.close_out_followup_nature ? ` — ${nonconformity.close_out_followup_nature}` : ""}`,
  ].filter(Boolean);

  const attachments = Object.entries(ATTACHMENT_LABELS)
    .filter(([key]) => nonconformity[key as keyof typeof ATTACHMENT_LABELS])
    .map(([, label]) => label);
  if (nonconformity.attach_others) {
    attachments.push(`Others — ${nonconformity.attach_others_details || ""}`.trim());
  }

  return (
    <Modal title={`Non Conformity — ${nonconformity.ncr_no}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Section title="Header">
          <Row label="NCR No." value={nonconformity.ncr_no} />
          <Row label="Date of NC" value={nonconformity.date_of_nc} />
          <Row label="Vessel/Company" value={nonconformity.vessel_company} />
          <Row label="Department" value={nonconformity.department_name} />
          <Row label="Reporter" value={`${nonconformity.reported_by_raw ?? ""} - ${nonconformity.reporter_name ?? ""}`} />
        </Section>

        <Section title="Source of Non Conformance">
          <Row
            label="Source"
            value={
              nonconformity.source_of_nc_raw === "OTHERS"
                ? `NC - OTHERS (${nonconformity.source_of_nc_others})`
                : nonconformity.source_of_nc_raw === "OPERATIONAL"
                  ? "NC - OPERATIONAL"
                  : nonconformity.source_of_nc_raw
            }
          />
          <Row label="Source Ref. No." value={nonconformity.source_of_nc_ref_no} />
          <Row label="SMS Procedure / ISM Code Affected" value={nonconformity.manual_chapter_label} />
        </Section>

        <Section title="Description of Non Conformity">
          <p className="whitespace-pre-wrap text-sm text-slate-800">{nonconformity.description}</p>
        </Section>

        <Section title="Root Cause of Non Conformity">
          <p className="whitespace-pre-wrap text-sm text-slate-800">{nonconformity.root_cause || "—"}</p>
          <Row label="Person In-charge" value={nonconformity.root_cause_incharge} />
        </Section>

        <Section title="Proposed Corrective Action(s)">
          <p className="whitespace-pre-wrap text-sm text-slate-800">{nonconformity.corrective_action || "—"}</p>
          <Row label="Person In-charge" value={nonconformity.corrective_action_incharge} />
          <Row label="Target Date of Completion" value={nonconformity.corrective_action_date} />
        </Section>

        <Section title="Verification of Corrective Action">
          <p className="text-sm text-slate-800">{verificationLabel ?? "—"}</p>
          <Row label="DPA / Safety Management Committee" value={nonconformity.verification_dpa} />
          <Row label="Verification Date" value={nonconformity.verification_date} />
        </Section>

        <Section title="Close Out">
          <p className="text-sm text-slate-800">{closeOutParts.length > 0 ? closeOutParts.join(" · ") : "—"}</p>
          <Row label="Designated Person Ashore" value={nonconformity.close_out_dpa} />
          <Row label="Close Out Date" value={nonconformity.close_out_date} />
        </Section>

        <Section title="Attached Documents">
          <p className="text-sm text-slate-800">{attachments.length > 0 ? attachments.join(" · ") : "—"}</p>
        </Section>
      </div>
    </Modal>
  );
}
