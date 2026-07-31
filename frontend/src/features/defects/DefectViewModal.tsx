import { CATEGORY_LABELS, COMPL_CODE_LABELS, PRIORITY_LABELS, RAISED_BY_LABELS } from "./defects";
import type { DefectDetail } from "./defects";
import { Modal } from "../../components/ui/Modal";

interface DefectViewModalProps {
  defect: DefectDetail;
  onClose: () => void;
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs font-semibold text-slate-500">{label}</p>
      <p className="text-sm text-slate-800">{value || "—"}</p>
    </div>
  );
}

/** Ported from admin/defect_list/view_defect_list.php. Not ported: Attached Files (no file storage in this migration) and Print. */
export function DefectViewModal({ defect, onClose }: DefectViewModalProps) {
  return (
    <Modal title={`Defect List — ${defect.sl_no}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Vessel" value={defect.vessel} />
          <Field label="Date" value={defect.defect_date} />
        </div>
        <Field label="Defect Description" value={defect.description} />
        <Field label="Present Status" value={defect.present_status ?? ""} />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Priority" value={defect.priority ? PRIORITY_LABELS[defect.priority] : ""} />
          <Field label="Cat" value={defect.category ? CATEGORY_LABELS[defect.category] : ""} />
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Raised By" value={defect.raised_by ? RAISED_BY_LABELS[defect.raised_by] : ""} />
          <Field label="Compl Code" value={COMPL_CODE_LABELS[defect.compl_code] ?? defect.compl_code} />
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Expected Compl Date" value={defect.expected_compl_date ?? ""} />
          <Field label="Compl Date" value={defect.compl_date ?? ""} />
        </div>
        <Field label="Vessel Remarks" value={defect.vessel_remarks ?? ""} />
        <Field label="Shore Remarks" value={defect.shore_remarks ?? ""} />

        <div className="flex justify-end border-t border-slate-100 pt-4">
          <button
            type="button"
            onClick={onClose}
            className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
          >
            Close
          </button>
        </div>
      </div>
    </Modal>
  );
}
