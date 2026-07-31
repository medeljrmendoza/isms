import { useEffect, useState } from "react";
import { pmsRunningHoursService } from "./pmsRunningHoursService";
import type { PmsRunningHoursOption, PmsRunningHoursPeriod, PmsRunningHoursRow } from "./pmsRunningHours";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { Alert } from "../../components/ui/Alert";
import { isApiValidationError } from "../auth/auth";
import axios from "axios";

function daysInMonth(month: number, year: number): number {
  return new Date(year, month, 0).getDate();
}

const MONTH_NAMES = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December",
];

interface UpdateModalState {
  equipmentId: number;
  equipmentName: string;
}

/** Ported from admin/pms_running_hours_equipments/running_hours_v.php. */
export function PmsRunningHoursPage() {
  const [vessels, setVessels] = useState<PmsRunningHoursOption[]>([]);
  const [vesselId, setVesselId] = useState("");
  const [appliedVesselId, setAppliedVesselId] = useState("");
  const [filterError, setFilterError] = useState<string | null>(null);

  const [currentPeriod, setCurrentPeriod] = useState<PmsRunningHoursPeriod | null>(null);
  const [periodOptions, setPeriodOptions] = useState<PmsRunningHoursPeriod[]>([]);
  const [selectedPeriod, setSelectedPeriod] = useState<string>("");

  const [rows, setRows] = useState<PmsRunningHoursRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [rollingOver, setRollingOver] = useState(false);

  const [updateModal, setUpdateModal] = useState<UpdateModalState | null>(null);
  const [updateDate, setUpdateDate] = useState(new Date().toISOString().slice(0, 10));
  const [updateHours, setUpdateHours] = useState("");
  const [updateRemarks, setUpdateRemarks] = useState("");
  const [updateError, setUpdateError] = useState<string | null>(null);
  const [updateSubmitting, setUpdateSubmitting] = useState(false);

  useEffect(() => {
    pmsRunningHoursService.options().then((data) => setVessels(data.vessels)).catch(() => undefined);
  }, []);

  const load = (vId: number, period?: string) => {
    setLoading(true);
    const [month, year] = period ? period.split("-").map(Number) : [undefined, undefined];
    pmsRunningHoursService
      .list(vId, month, year)
      .then((data) => {
        setCurrentPeriod(data.current_period);
        setPeriodOptions(data.period_options);
        setRows(data.rows);
        if (!period && data.current_period) {
          setSelectedPeriod(`${data.current_period.month}-${data.current_period.year}`);
        }
        setError(null);
      })
      .catch(() => setError("Couldn't load running hours. Please try again."))
      .finally(() => setLoading(false));
  };

  const handleSearch = () => {
    if (!vesselId) {
      setFilterError("Please select Vessel.");
      return;
    }
    setFilterError(null);
    setAppliedVesselId(vesselId);
    setSelectedPeriod("");
    load(Number(vesselId));
  };

  const handlePeriodChange = (value: string) => {
    setSelectedPeriod(value);
    if (appliedVesselId) {
      load(Number(appliedVesselId), value);
    }
  };

  const handleProceedNextMonth = async () => {
    if (!appliedVesselId) return;
    if (!window.confirm("Proceed to next month? This archives the current month and resets the entry grid.")) return;
    setRollingOver(true);
    try {
      await pmsRunningHoursService.proceedNextMonth(Number(appliedVesselId));
      setSelectedPeriod("");
      load(Number(appliedVesselId));
    } catch {
      setError("Couldn't proceed to next month. Please try again.");
    } finally {
      setRollingOver(false);
    }
  };

  const openUpdateModal = (row: PmsRunningHoursRow) => {
    setUpdateModal({ equipmentId: row.equipment_id, equipmentName: row.equipment_name });
    setUpdateDate(new Date().toISOString().slice(0, 10));
    setUpdateHours("");
    setUpdateRemarks("");
    setUpdateError(null);
  };

  const submitUpdate = async () => {
    if (!updateModal) return;
    setUpdateError(null);
    const hours = Number(updateHours);
    if (!updateDate) {
      setUpdateError("Date is required.");
      return;
    }
    if (!hours || hours <= 0) {
      setUpdateError("Hours must be greater than 0.");
      return;
    }
    setUpdateSubmitting(true);
    try {
      await pmsRunningHoursService.update({
        equipment_id: updateModal.equipmentId,
        date: updateDate,
        hours,
        remarks: updateRemarks || undefined,
      });
      setUpdateModal(null);
      if (appliedVesselId) {
        load(Number(appliedVesselId), selectedPeriod || undefined);
      }
    } catch (err) {
      if (axios.isAxiosError(err) && isApiValidationError(err.response?.data)) {
        const messages = Object.values(err.response.data.errors).flat();
        setUpdateError(messages[0] ?? "Something went wrong.");
      } else {
        setUpdateError("Something went wrong. Please try again.");
      }
    } finally {
      setUpdateSubmitting(false);
    }
  };

  const [selMonth, selYear] = selectedPeriod ? selectedPeriod.split("-").map(Number) : [currentPeriod?.month, currentPeriod?.year];
  const dayCount = selMonth && selYear ? daysInMonth(selMonth, selYear) : 31;
  const isViewingCurrent = !!currentPeriod && selMonth === currentPeriod.month && selYear === currentPeriod.year;

  return (
    <div className="p-6">
      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
          <h1 className="text-base font-semibold text-slate-800">PMS Running Hours (Component Lists)</h1>
          {currentPeriod && (
            <span className="rounded bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-700">
              Current Calendar: {MONTH_NAMES[currentPeriod.month - 1]} {currentPeriod.year}
            </span>
          )}
        </div>

        <div className="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
          <div className="flex flex-col gap-1">
            <label className="text-xs font-medium text-slate-500">Vessel</label>
            <select value={vesselId} onChange={(e) => setVesselId(e.target.value)} className="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">Select vessel...</option>
              {vessels.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.label}
                </option>
              ))}
            </select>
          </div>
          <Button type="button" variant="primary" className="!px-3 !py-1.5 text-sm" onClick={handleSearch}>
            Search
          </Button>
          {filterError && <p className="text-sm text-red-600">{filterError}</p>}

          {appliedVesselId && periodOptions.length > 0 && (
            <div className="flex flex-col gap-1">
              <label className="text-xs font-medium text-slate-500">Calendar</label>
              <select
                value={selectedPeriod}
                onChange={(e) => handlePeriodChange(e.target.value)}
                className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
              >
                {periodOptions.map((p) => (
                  <option key={`${p.month}-${p.year}`} value={`${p.month}-${p.year}`}>
                    {p.label}
                  </option>
                ))}
              </select>
            </div>
          )}

          {appliedVesselId && (
            <Button type="button" variant="secondary" className="!px-3 !py-1.5 text-sm" onClick={handleProceedNextMonth} isLoading={rollingOver}>
              Proceed to Next Month
            </Button>
          )}
        </div>

        {error && <p className="px-4 pt-2 text-sm text-red-600">{error}</p>}

        <div className="overflow-x-auto px-4 py-3">
          {!appliedVesselId ? (
            <p className="py-6 text-center text-sm text-slate-400">Select a vessel and click Search.</p>
          ) : loading ? (
            <div className="flex items-center gap-2 py-2 text-xs text-slate-400">
              <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600" />
              Loading...
            </div>
          ) : (
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50">
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">COMPONENT CODE</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">COMPONENT</th>
                  <th className="whitespace-nowrap px-2 py-1.5 font-semibold text-slate-600">TOTAL RH SINCE DELIVERY</th>
                  {Array.from({ length: dayCount }, (_, i) => i + 1).map((day) => (
                    <th key={day} className="px-1.5 py-1.5 text-center font-semibold text-slate-600">
                      {day}
                    </th>
                  ))}
                  <th className="px-2 py-1.5 font-semibold text-slate-600">ACTION</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.equipment_id} className="border-b border-slate-100">
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.equipment_code}</td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.equipment_name}</td>
                    <td className="whitespace-nowrap px-2 py-1.5 text-slate-700">{row.since_delivery ?? "—"}</td>
                    {Array.from({ length: dayCount }, (_, i) => i + 1).map((day) => (
                      <td key={day} className="px-1.5 py-1.5 text-center text-slate-700">
                        {row.daily_hours[String(day)] ?? ""}
                      </td>
                    ))}
                    <td className="whitespace-nowrap px-2 py-1.5">
                      {row.update_by_component && (
                        <Button type="button" variant="secondary" className="!px-1.5 !py-0.5 text-xs" onClick={() => openUpdateModal(row)}>
                          Update
                        </Button>
                      )}
                    </td>
                  </tr>
                ))}
                {rows.length === 0 && (
                  <tr>
                    <td colSpan={dayCount + 4} className="px-2 py-6 text-center text-sm text-slate-400">
                      No components tracked for this vessel.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          )}
          {appliedVesselId && !isViewingCurrent && !loading && rows.length > 0 && (
            <p className="pt-2 text-xs text-slate-400">Viewing a past month's snapshot.</p>
          )}
        </div>
      </div>

      {updateModal && (
        <Modal title={`Update Running Hours — ${updateModal.equipmentName}`} onClose={() => setUpdateModal(null)}>
          <div className="flex flex-col gap-4">
            {updateError && <Alert variant="error">{updateError}</Alert>}
            <TextField label="Date" type="date" value={updateDate} onChange={(e) => setUpdateDate(e.target.value)} />
            <TextField label="No. of Hours" type="number" step="0.01" value={updateHours} onChange={(e) => setUpdateHours(e.target.value)} />
            <TextareaField label="Remarks" value={updateRemarks} onChange={(e) => setUpdateRemarks(e.target.value)} />
            <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
              <Button type="button" variant="secondary" onClick={() => setUpdateModal(null)}>
                Cancel
              </Button>
              <Button type="button" variant="success" isLoading={updateSubmitting} onClick={submitUpdate}>
                Save
              </Button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}
