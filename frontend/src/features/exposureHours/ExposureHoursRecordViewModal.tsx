import type { ExposureHoursRecordDetail } from "./exposureHours";
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

export function ExposureHoursRecordViewModal({ record: r, onClose }: { record: ExposureHoursRecordDetail; onClose: () => void }) {
  return (
    <Modal title={`Exposure Hours — ${r.vessel}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Section title="Header">
          <Row label="Vessel" value={r.vessel} />
          <Row label="Added By" value={r.added_by} />
          <Row label="Date From" value={r.date_from} />
          <Row label="Date To" value={r.date_to} />
          <Row label="# of Crew" value={r.no_of_crew} />
        </Section>

        <Section title="Incident Counts">
          <Row label="FAT" value={r.no_of_fat} />
          <Row label="PTD" value={r.no_of_ptd} />
          <Row label="PPD" value={r.no_of_ppd} />
          <Row label="LWC" value={r.no_of_lwc} />
          <Row label="RWC" value={r.no_of_rwc} />
          <Row label="MTC" value={r.no_of_mtc} />
          <Row label="Total Hours" value={r.total_hours} />
        </Section>

        <Section title="Remarks">
          <Row label="Vessel Remarks" value={r.vessel_remarks} />
          <Row label="Shore Remarks" value={r.shore_remarks} />
        </Section>
      </div>
    </Modal>
  );
}
