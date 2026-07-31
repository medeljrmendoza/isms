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

interface PostponeModalProps {
  activity: PmsActivityRow;
  onClose: () => void;
  onSuccess: () => void;
}

/** Ported from admin/pms_activities/postpone_v.php. */
export function PostponeModal({ activity, onClose, onSuccess }: PostponeModalProps) {
  const [postponeDate, setPostponeDate] = useState(new Date().toISOString().slice(0, 10));
  const [description, setDescription] = useState("");
  const [cause, setCause] = useState("");
  const [remarks, setRemarks] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const submit = async () => {
    setError(null);

    if (!postponeDate) {
      setError("Date Postponed is required.");
      return;
    }
    if (!description.trim()) {
      setError("Description is required.");
      return;
    }
    if (!cause.trim()) {
      setError("Possible Cause is required.");
      return;
    }

    setSubmitting(true);
    try {
      await pmsActivitiesService.postpone(activity.id, {
        postpone_date: postponeDate,
        description,
        possible_cause: cause,
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
    <Modal title={`Postpone Activity — ${activity.activity_name}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        {error && <Alert variant="error">{error}</Alert>}

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <TextField label="Component" disabled readOnly value={activity.equipment_name ?? ""} />
          <TextField label="Part" disabled readOnly value={activity.part_name ?? ""} />
        </div>
        <TextField label="Activity" disabled readOnly value={activity.activity_name} />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <TextField label="Frequency" disabled readOnly value={activity.frequency ?? ""} />
          <TextField label="In-charge" disabled readOnly value={activity.incharge ?? ""} />
        </div>

        <TextField
          label="Date Postponed"
          type="date"
          max={new Date().toISOString().slice(0, 10)}
          value={postponeDate}
          onChange={(e) => setPostponeDate(e.target.value)}
        />
        <TextareaField label="Description" value={description} onChange={(e) => setDescription(e.target.value)} />
        <TextareaField label="Possible Cause" value={cause} onChange={(e) => setCause(e.target.value)} />
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
