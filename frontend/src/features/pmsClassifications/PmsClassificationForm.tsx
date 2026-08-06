import { useEffect, useState, type FormEvent } from "react";
import axios from "axios";
import { pmsClassificationsService } from "./pmsClassificationsService";
import type { PmsClassificationDetail, PmsClassificationOption } from "./pmsClassifications";
import { isApiValidationError } from "../auth/auth";
import { Button } from "../../components/ui/Button";

interface PmsClassificationFormProps {
  record?: PmsClassificationDetail;
  departments: PmsClassificationOption[];
  vesselTypes: PmsClassificationOption[];
  onCancel: () => void;
  onSuccess: () => void;
}

/** Ported from add_pms_setup_classification.php. */
export function PmsClassificationForm({ record, departments, vesselTypes, onCancel, onSuccess }: PmsClassificationFormProps) {
  const [name, setName] = useState(record?.name ?? "");
  const [description, setDescription] = useState(record?.description ?? "");
  const [departmentIds, setDepartmentIds] = useState<(number | string)[]>(record?.departments.map((d) => d.id) ?? []);
  const [vesselTypeIds, setVesselTypeIds] = useState<(number | string)[]>(record?.vessel_types.map((v) => v.id) ?? []);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    setDepartmentIds(record?.departments.map((d) => d.id) ?? []);
    setVesselTypeIds(record?.vessel_types.map((v) => v.id) ?? []);
  }, [record]);

  const toggleDepartment = (id: number | string) => {
    setDepartmentIds((prev) => (prev.includes(id) ? prev.filter((d) => d !== id) : [...prev, id]));
  };

  const toggleVesselType = (id: number | string) => {
    setVesselTypeIds((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (!name.trim()) {
      setError("Required field");
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      const values = { name, description, department_ids: departmentIds, vessel_type_ids: vesselTypeIds };
      if (record) {
        await pmsClassificationsService.update(record.id, values);
      } else {
        await pmsClassificationsService.create(values);
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
        <input
          type="text"
          value={name}
          onChange={(e) => setName(e.target.value.toUpperCase())}
          className="rounded-md border border-slate-300 px-2 py-1.5 text-sm uppercase"
          autoFocus
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

      <div className="flex flex-col gap-1">
        <label className="text-xs font-medium text-slate-500">Department:</label>
        <label className="flex items-center gap-1.5 text-xs text-slate-600">
          <input
            type="checkbox"
            checked={departments.length > 0 && departmentIds.length === departments.length}
            onChange={(e) => setDepartmentIds(e.target.checked ? departments.map((d) => d.id) : [])}
          />
          SELECT ALL
        </label>
        <div className="grid grid-cols-2 gap-1 rounded-md border border-slate-200 p-2">
          {departments.map((d) => (
            <label key={d.id} className="flex items-center gap-1.5 text-sm text-slate-700">
              <input type="checkbox" checked={departmentIds.includes(d.id)} onChange={() => toggleDepartment(d.id)} />
              {d.label}
            </label>
          ))}
        </div>
      </div>

      <div className="flex flex-col gap-1">
        <label className="text-xs font-medium text-slate-500">Vessel Type:</label>
        <label className="flex items-center gap-1.5 text-xs text-slate-600">
          <input
            type="checkbox"
            checked={vesselTypes.length > 0 && vesselTypeIds.length === vesselTypes.length}
            onChange={(e) => setVesselTypeIds(e.target.checked ? vesselTypes.map((v) => v.id) : [])}
          />
          SELECT ALL
        </label>
        <div className="grid grid-cols-2 gap-1 rounded-md border border-slate-200 p-2">
          {vesselTypes.map((v) => (
            <label key={v.id} className="flex items-center gap-1.5 text-sm text-slate-700">
              <input type="checkbox" checked={vesselTypeIds.includes(v.id)} onChange={() => toggleVesselType(v.id)} />
              {v.label}
            </label>
          ))}
        </div>
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
