import type { CompanyInspectionDetail } from "./companyInspection";
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

/** Ported from admin/companyinspection/view_company_report.php. */
export function CompanyInspectionViewModal({
  companyInspection: r,
  onClose,
}: {
  companyInspection: CompanyInspectionDetail;
  onClose: () => void;
}) {
  const isVessel = r.vessel_company_raw === "VESSEL";

  return (
    <Modal title={`Company Inspection — ${r.vessel_company}`} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Section title="Header">
          <Row label="Ref. No." value={r.audit_ref} />
          <Row label={isVessel ? "Vessel" : "Company"} value={r.vessel_company} />
          <Row label="Department" value={r.department} />
          <Row label="Date of Inspection" value={r.this_date} />
          <Row label="Port of Inspection" value={r.placeof_audit} />
          <Row label="Type of Inspection" value={r.audit_type} />
          <Row label="Kind of Inspection" value={r.audit_kind} />
          <Row label="Inspector" value={r.inspector_name} />
          {isVessel && (
            <>
              <Row label="Master" value={r.master_name} />
              <Row label="Chief Engineer" value={r.chief_engineer} />
            </>
          )}
        </Section>

        <Section title="Non-conformities">
          <Row label="Pending / Total" value={`${r.pending_nc_count} / ${r.total_nc_count}`} />
        </Section>

        <Section title="Remarks">
          <p className="whitespace-pre-wrap text-sm text-slate-800">{r.remarks || "—"}</p>
        </Section>
      </div>
    </Modal>
  );
}
