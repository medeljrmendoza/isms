import { useEffect, useState } from "react";
import { drillService } from "./drillService";
import type { DrillCellItem } from "./drill";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";

interface DrillCellModalProps {
  drillListId: number | string;
  drillName: string;
  vesselId: number | string;
  year: number;
  month: number;
  onClose: () => void;
  onView: (reportId: number | string) => void;
  onEdit: (reportId: number | string) => void;
}

const MONTH_NAMES = ["", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

/** Ported from view_calendar_reports() — the flat list of reports behind one calendar cell. */
export function DrillCellModal({ drillListId, drillName, vesselId, year, month, onClose, onView, onEdit }: DrillCellModalProps) {
  const [items, setItems] = useState<DrillCellItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    drillService
      .cell(drillListId, vesselId, year, month)
      .then(setItems)
      .finally(() => setLoading(false));
  }, [drillListId, vesselId, year, month]);

  return (
    <Modal title={`${drillName} — ${MONTH_NAMES[month]} ${year}`} onClose={onClose}>
      {loading && <p className="text-sm text-slate-400">Loading...</p>}
      {!loading && items.length === 0 && <p className="text-sm text-slate-400">No reports.</p>}
      {!loading && items.length > 0 && (
        <table className="w-full text-left text-sm">
          <thead>
            <tr className="border-b border-slate-200">
              <th className="px-2 py-1.5 font-semibold text-slate-600">DATE</th>
              <th className="px-2 py-1.5 font-semibold text-slate-600">POSITION</th>
              <th className="px-2 py-1.5 font-semibold text-slate-600">TIME</th>
              <th className="px-2 py-1.5 font-semibold text-slate-600">ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr key={item.id} className="border-b border-slate-100">
                <td className="px-2 py-1.5">
                  <button type="button" className="text-blue-600 hover:underline" onClick={() => onView(item.id)}>
                    {item.drill_date}
                  </button>
                </td>
                <td className="px-2 py-1.5 text-slate-700">{item.drill_position ?? "—"}</td>
                <td className="px-2 py-1.5 text-slate-700">{item.drill_time_from ?? "—"}</td>
                <td className="px-2 py-1.5">
                  {item.can_edit && (
                    <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => onEdit(item.id)}>
                      Edit
                    </Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Modal>
  );
}
