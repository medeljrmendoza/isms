import { useState, type FormEvent } from "react";
import { pmsDepartmentService } from "./pmsDepartmentService";
import type { PmsDepartmentRow } from "./pmsDepartment";
import { Button } from "../../components/ui/Button";

interface PmsDepartmentFormProps {
  record?: PmsDepartmentRow;
  onCancel: () => void;
  onSuccess: () => void;
}

/** Ported from add_pms_setup_department_v.php. */
export function PmsDepartmentForm({ record, onCancel, onSuccess }: PmsDepartmentFormProps) {
  const [name, setName] = useState(record?.name ?? "");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (!name.trim()) {
      setError("Required field");
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      if (record) {
        await pmsDepartmentService.update(record.id, name);
      } else {
        await pmsDepartmentService.create(name);
      }
      onSuccess();
    } catch {
      setError("Data was not saved. Please try again.");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex flex-col gap-1">
        <label className="text-xs font-medium text-slate-500">
          Department Name: <sup>*</sup>
        </label>
        <input
          type="text"
          value={name}
          onChange={(e) => setName(e.target.value.toUpperCase())}
          className="rounded-md border border-slate-300 px-2 py-1.5 text-sm uppercase"
          autoFocus
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
