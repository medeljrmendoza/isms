import { useState, type FormEvent } from "react";
import axios from "axios";
import { pmsClassificationsService } from "./pmsClassificationsService";
import type { PmsSubClassificationRow } from "./pmsClassifications";
import { isApiValidationError } from "../auth/auth";
import { Button } from "../../components/ui/Button";

interface PmsSubClassificationFormProps {
  classificationId: number;
  classificationName: string;
  record?: PmsSubClassificationRow;
  onCancel: () => void;
  onSuccess: () => void;
}

/** Ported from add_pms_setup_sub_classification.php. */
export function PmsSubClassificationForm({ classificationId, classificationName, record, onCancel, onSuccess }: PmsSubClassificationFormProps) {
  const [chartCode, setChartCode] = useState(record?.chart_code ?? "");
  const [name, setName] = useState(record?.name ?? "");
  const [description, setDescription] = useState(record?.description ?? "");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (!chartCode.trim() || !name.trim()) {
      setError("Required field");
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      const values = { chart_code: chartCode, name, description };
      if (record) {
        await pmsClassificationsService.subUpdate(record.id, values);
      } else {
        await pmsClassificationsService.subCreate(classificationId, values);
      }
      onSuccess();
    } catch (err) {
      if (axios.isAxiosError(err) && isApiValidationError(err.response?.data)) {
        const message = Object.values(err.response.data.errors).flat()[0];
        setError(message ?? "Data was not saved.");
      } else {
        setError("Data was not saved. Please try again.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex flex-col gap-1">
        <label className="text-xs font-medium text-slate-500">
          Classification Name: <sup>*</sup>
        </label>
        <p className="text-sm font-medium text-slate-800">{classificationName}</p>
      </div>

      <div className="flex flex-col gap-1">
        <label className="text-xs font-medium text-slate-500">
          Chart Code: <sup>*</sup>
        </label>
        <input
          type="text"
          value={chartCode}
          onChange={(e) => setChartCode(e.target.value.toUpperCase())}
          className="rounded-md border border-slate-300 px-2 py-1.5 text-sm uppercase"
          autoFocus
        />
      </div>

      <div className="flex flex-col gap-1">
        <label className="text-xs font-medium text-slate-500">
          Sub-Classification Name: <sup>*</sup>
        </label>
        <input
          type="text"
          value={name}
          onChange={(e) => setName(e.target.value.toUpperCase())}
          className="rounded-md border border-slate-300 px-2 py-1.5 text-sm uppercase"
        />
      </div>

      <div className="flex flex-col gap-1">
        <label className="text-xs font-medium text-slate-500">Description:</label>
        <textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          rows={3}
          className="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
        />
      </div>

      {error && <p className="text-sm text-red-600">{error}</p>}

      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variant="secondary" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="submit" variant="success" disabled={submitting}>
          Save
        </Button>
      </div>
    </form>
  );
}
