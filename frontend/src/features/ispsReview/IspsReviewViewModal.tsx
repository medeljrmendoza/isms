import type { IspsReviewDetail } from "./ispsReview";
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

/** Ported from admin/isps_review/view_isps_review.php. */
export function IspsReviewViewModal({ review: r, onClose }: { review: IspsReviewDetail; onClose: () => void }) {
  const isVessel = r.added_by === "VESSEL";

  return (
    <Modal title="ISPS Review" onClose={onClose}>
      <div className="flex flex-col gap-3">
        <Section title="Report">
          {r.vessel && <Row label="Vessel" value={r.vessel} />}
          <Row label="Date" value={r.review_date} />
          <Row label="Quarter" value={r.review_quarter} />
          <Row label="Year" value={r.review_year} />
          <Row label="Manual / Procedure" value={r.sms} />
          <Row label="Description" value={r.review_description} />
          <Row label="Recommendation" value={r.review_recommendation} />
          <Row label="Reviewed By" value={isVessel ? r.vessel_reviewed_by : r.shore_reviewed_by} />
          {isVessel && <Row label="Position" value={r.vessel_reviewed_position} />}
        </Section>

        {isVessel && (
          <Section title="Vessel Remarks">
            <Row label="Remarks" value={r.vessel_remarks} />
          </Section>
        )}

        <Section title="Shore Remarks">
          <Row label="Remarks" value={r.shore_remarks} />
        </Section>

        <Section title="Status">
          <Row label="Status" value={r.shore_status || "PENDING"} />
        </Section>

        <Section title="Present During Review">
          {r.present.length === 0 && <p className="text-sm text-slate-400">No attendees recorded.</p>}
          {r.present.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="border-b border-slate-200">
                    <th className="px-1.5 py-1 font-semibold text-slate-600">Name</th>
                    <th className="px-1.5 py-1 font-semibold text-slate-600">Position</th>
                  </tr>
                </thead>
                <tbody>
                  {r.present.map((p, i) => (
                    <tr key={p.id ?? i} className="border-b border-slate-100">
                      <td className="px-1.5 py-1 text-slate-700">{p.name}</td>
                      <td className="px-1.5 py-1 text-slate-700">{p.position ?? "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Section>
      </div>
    </Modal>
  );
}
