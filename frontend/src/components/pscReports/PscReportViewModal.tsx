import type { PscReportDetail } from "../../types/pscReport";
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

/** Ported from admin/psc/view_psc_report.php. */
export function PscReportViewModal({ pscReport: r, onClose }: { pscReport: PscReportDetail; onClose: () => void }) {
  return (
    <Modal title={`PSC Inspection — ${r.vessel}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Section title="Header">
          <Row label="Reference No." value={r.ref_no} />
          <Row label="Vessel" value={r.vessel} />
          <Row label="Date of Inspection" value={r.dateof_inspection} />
          <Row label="Place of Inspection" value={r.placeof_inspection} />
          <Row label="MOU / Authority" value={r.mou} />
          <Row label="Name of Authorized PSCO" value={r.name_psco} />
          <Row label="Master" value={r.master_name} />
          <Row label="Chief Engineer" value={r.chief_engineer} />
        </Section>

        <Section title="Detention">
          <Row label="Vessel Detained?" value={r.is_detained ? "Yes" : "No"} />
          {r.is_detained && (
            <>
              <Row label="Date/Time Detained" value={`${r.detained_date ?? "—"} ${r.detained_time ?? ""}`.trim()} />
              <Row label="Vessel Released?" value={r.is_released ? "Yes" : "No"} />
              {r.is_released && <Row label="Date/Time Released" value={`${r.released_date ?? "—"} ${r.released_time ?? ""}`.trim()} />}
            </>
          )}
        </Section>

        <Section title="Non-conformities">
          <Row label="Pending / Total" value={`${r.pending_nc_count} / ${r.total_nc_count}`} />
        </Section>

        <Section title="Remarks">
          <Row label="Closing Date" value={r.closing_date} />
          <p className="whitespace-pre-wrap text-sm text-slate-800">{r.remarks || "—"}</p>
        </Section>
      </div>
    </Modal>
  );
}
