import type { CommitteeMeetingDetail } from "./committeeMeeting";
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

/** Ported from admin/commiteemeeting/view_committee_meeting.php. */
export function CommitteeMeetingViewModal({ committeeMeeting: m, onClose }: { committeeMeeting: CommitteeMeetingDetail; onClose: () => void }) {
  const typeLabel = m.meeting_types.map((t) => (t.name === "OTHERS" && t.type_other ? `${t.name} (${t.type_other})` : t.name)).join(", ");

  return (
    <Modal title={`Committee Meeting — ${m.vessel}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Section title="Header">
          <Row label="Scope" value={m.shore_vessel_meeting} />
          <Row label="Vessel" value={m.vessel} />
          <Row label="Added By" value={m.added_by} />
          <Row label="Position" value={m.meeting_position} />
          <Row label="Date" value={m.meeting_date} />
          <Row label="Time" value={m.meeting_time} />
          <Row label="Type of Meeting" value={typeLabel} />
        </Section>

        <Section title="Matters Discussed">
          {m.topics.length === 0 && <p className="text-sm text-slate-400">No topics recorded.</p>}
          <div className="flex flex-col gap-2">
            {m.topics.map((t, i) => (
              <div key={i} className="rounded border border-slate-100 p-2 text-sm">
                <div className="font-medium text-slate-700">{t.topic_name}</div>
                {t.meeting_details && <div className="mt-1 text-slate-600">Details: {t.meeting_details}</div>}
                {t.meeting_comments && <div className="mt-1 text-slate-600">Shore Comments: {t.meeting_comments}</div>}
              </div>
            ))}
          </div>
        </Section>

        <Section title="Members">
          <Row label="Members" value={m.members.map((mem) => mem.name).join(", ")} />
        </Section>

        <Section title="In Attendance">
          <Row label="Attendees" value={m.attendees.map((a) => a.name).join(", ")} />
        </Section>

        <Section title="People">
          <Row label="Chairman" value={m.chairman} />
          <Row label="In-charge" value={m.incharge} />
        </Section>

        <Section title="Status">
          <Row label="Published" value={flagLabel(m.published)} />
          <Row label="Approved" value={flagLabel(m.is_approved)} />
        </Section>

        <Section title="Remarks">
          <Row label="Vessel Remarks" value={m.vessel_remarks} />
          <Row label="Shore Remarks" value={m.shore_remarks} />
        </Section>
      </div>
    </Modal>
  );
}
