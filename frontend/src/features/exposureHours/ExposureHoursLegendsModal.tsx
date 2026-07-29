import { Modal } from "../../components/ui/Modal";

const LEGENDS = [
  { code: "FAT", label: "Fatality" },
  { code: "PTD", label: "Permanent Total Disability" },
  { code: "PPD", label: "Permanent Partial Disability" },
  { code: "LWC", label: "Lost Workday Case" },
  { code: "RWC", label: "Restricted Work Case" },
  { code: "MTC", label: "Medical Treatment Case" },
  { code: "LTI", label: "Lost Time Injuries = FAT + PTD + PPD + LWC" },
  { code: "TRC", label: "Total Recordable Cases = LTI + RWC + MTC" },
  { code: "LTIF", label: "Lost Time Injury Frequency = (LTI × 1,000,000) ÷ Total Exposure Hours" },
  { code: "TRCF", label: "Total Recordable Case Frequency = (TRC × 1,000,000) ÷ Total Exposure Hours" },
];

/** Ported from admin/exposurehours/legends_v.php — a static glossary, no backend data. */
export function ExposureHoursLegendsModal({ onClose }: { onClose: () => void }) {
  return (
    <Modal title="Exposure Hours — Legends" onClose={onClose}>
      <div className="flex flex-col gap-2">
        {LEGENDS.map((item) => (
          <div key={item.code} className="grid grid-cols-4 gap-2 border-b border-slate-100 py-1.5 text-sm last:border-0">
            <span className="font-semibold text-slate-700">{item.code}</span>
            <span className="col-span-3 text-slate-700">{item.label}</span>
          </div>
        ))}
      </div>
    </Modal>
  );
}
