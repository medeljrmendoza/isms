import { useState } from "react";
import axios from "axios";
import type { PmsActivityRow } from "./pmsActivities";
import { pmsActivitiesService } from "./pmsActivitiesService";
import { isApiValidationError } from "../auth/auth";
import { Modal } from "../../components/ui/Modal";
import { Button } from "../../components/ui/Button";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { Alert } from "../../components/ui/Alert";

interface MarkDoneModalProps {
  activity: PmsActivityRow;
  onClose: () => void;
  onSuccess: () => void;
}

/** Ported from admin/pms_activities/update_activity_v.php. Not ported: the actual-inventory-used section — its Add-Item button/modal are commented out of the live template, so the feature is dead in production. */
export function MarkDoneModal({ activity, onClose, onSuccess }: MarkDoneModalProps) {
  const [lastDone, setLastDone] = useState(new Date().toISOString().slice(0, 10));
  const [unplanned, setUnplanned] = useState(false);
  const [description, setDescription] = useState("");
  const [cause, setCause] = useState("");
  const [remarks, setRemarks] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const submit = async () => {
    setError(null);

    if (!lastDone) {
      setError("Date of Activity is required.");
      return;
    }
    if (unplanned && !description.trim()) {
      setError("Please input Description for Unplanned Maintenance.");
      return;
    }
    if (unplanned && !cause.trim()) {
      setError("Please input Possible Causes for Unplanned Maintenance.");
      return;
    }

    setSubmitting(true);
    try {
      await pmsActivitiesService.markDone(activity.id, {
        last_done: lastDone,
        unplanned,
        unplanned_description: unplanned ? description : undefined,
        unplanned_cause: unplanned ? cause : undefined,
        remarks: remarks || undefined,
      });
      onSuccess();
    } catch (err) {
      if (axios.isAxiosError(err) && isApiValidationError(err.response?.data)) {
        const messages = Object.values(err.response.data.errors).flat();
        setError(messages[0] ?? "Something went wrong.");
      } else {
        setError("Something went wrong. Please try again.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Modal title={`Update Activity — ${activity.activity_name}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        {error && <Alert variant="error">{error}</Alert>}

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <TextField label="Component" disabled readOnly value={activity.equipment_name ?? ""} />
          <TextField label="Part" disabled readOnly value={activity.part_name ?? ""} />
        </div>
        <TextField label="Activity" disabled readOnly value={activity.activity_name} />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <TextField label="In-charge" disabled readOnly value={activity.incharge ?? ""} />
          <TextField label="Frequency" disabled readOnly value={activity.frequency ?? ""} />
        </div>

        <TextField label="Date of Activity" type="date" max={new Date().toISOString().slice(0, 10)} value={lastDone} onChange={(e) => setLastDone(e.target.value)} />

        <div className="flex flex-col gap-2">
          <span className="text-sm font-medium text-slate-700">Unplanned Maintenance?</span>
          <div className="flex gap-4 text-sm text-slate-700">
            <label className="flex items-center gap-1.5">
              <input type="radio" checked={unplanned} onChange={() => setUnplanned(true)} /> YES
            </label>
            <label className="flex items-center gap-1.5">
              <input type="radio" checked={!unplanned} onChange={() => setUnplanned(false)} /> NO
            </label>
          </div>
        </div>

        {unplanned && (
          <>
            <TextareaField label="Description" value={description} onChange={(e) => setDescription(e.target.value)} />
            <TextareaField label="Possible Causes" value={cause} onChange={(e) => setCause(e.target.value)} />
          </>
        )}

        <TextareaField label="Remarks" value={remarks} onChange={(e) => setRemarks(e.target.value)} />

        <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="button" variant="success" isLoading={submitting} onClick={submit}>
            Save
          </Button>
        </div>
      </div>
    </Modal>
  );
}
