import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildDefectSchema, type DefectFormValues } from "./defectsSchema";
import { defectsService } from "./defectsService";
import { CATEGORY_LABELS, COMPL_CODE_LABELS, PRIORITY_LABELS, RAISED_BY_LABELS } from "./defects";
import type { DefectDetail, DefectOption } from "./defects";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface DefectFormProps {
  defect?: DefectDetail;
  vessels: DefectOption[];
  onSuccess: () => void;
  onCancel: () => void;
}

function emptyValues(): DefectFormValues {
  return {
    vessel_id: "",
    sl_no: "",
    defect_date: new Date().toISOString().slice(0, 10),
    description: "",
    present_status: "",
    priority: "",
    category: "",
    raised_by: "",
    compl_code: "",
    expected_compl_date: "",
    compl_date: "",
    shore_remarks: "",
  };
}

function detailToFormValues(d: DefectDetail): DefectFormValues {
  return {
    vessel_id: d.vessel_id,
    sl_no: d.sl_no,
    defect_date: d.defect_date,
    description: d.description,
    present_status: d.present_status ?? "",
    priority: d.priority ?? "",
    category: d.category ?? "",
    raised_by: d.raised_by ?? "",
    compl_code: d.compl_code,
    expected_compl_date: d.expected_compl_date ?? "",
    compl_date: d.compl_date ?? "",
    shore_remarks: d.shore_remarks ?? "",
  };
}

/**
 * Ported from admin/defect_list/add_defect_list.php. Not ported: file
 * attachments (no file storage anywhere in this migration) and Vessel
 * Remarks (a vessel-origin field with no admin-side write path — no
 * VESSEL app exists in this migration — shown read-only, always blank,
 * matching the legacy disabled textarea). The legacy Vessel field is
 * itself disabled and never actually submitted (its input has no `name`
 * attribute), so a brand-new legacy record silently gets no vessel
 * assigned; here Vessel is a real required field on create and frozen
 * on edit, fixing that latent gap.
 */
export function DefectForm({ defect, vessels, onSuccess, onCancel }: DefectFormProps) {
  const isCreate = !defect;
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DefectFormValues>({
    resolver: zodResolver(buildDefectSchema(isCreate)),
    defaultValues: defect ? detailToFormValues(defect) : emptyValues(),
  });

  const onSubmit = async (values: DefectFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await defectsService.create(values);
      } else {
        await defectsService.update(defect.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof DefectFormValues, { message: messages[0] });
        });
        return;
      }
      setFormError("Something went wrong. Please try again.");
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
      {formError && <Alert variant="error">{formError}</Alert>}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {isCreate ? (
          <SelectField
            label="Vessel"
            placeholder="Select vessel..."
            options={vessels.map((v) => ({ value: String(v.id), label: v.label }))}
            error={errors.vessel_id?.message}
            {...register("vessel_id")}
          />
        ) : (
          <TextField
            label="Vessel"
            disabled
            value={vessels.find((v) => v.id === defect.vessel_id)?.label ?? ""}
            readOnly
          />
        )}
        <TextField label="SL No." error={errors.sl_no?.message} {...register("sl_no")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Date" type="date" error={errors.defect_date?.message} {...register("defect_date")} />
        <SelectField
          label="Compl Code"
          placeholder="Select"
          options={Object.entries(COMPL_CODE_LABELS).map(([value, label]) => ({ value, label }))}
          error={errors.compl_code?.message}
          {...register("compl_code")}
        />
      </div>

      <TextareaField label="Defect Description" error={errors.description?.message} {...register("description")} />
      <TextareaField label="Present Status" error={errors.present_status?.message} {...register("present_status")} />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <SelectField
          label="Priority"
          placeholder="Select"
          options={Object.entries(PRIORITY_LABELS).map(([value, label]) => ({ value, label }))}
          error={errors.priority?.message}
          {...register("priority")}
        />
        <SelectField
          label="Cat"
          placeholder="Select"
          options={Object.entries(CATEGORY_LABELS).map(([value, label]) => ({ value, label }))}
          error={errors.category?.message}
          {...register("category")}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <SelectField
          label="Raised By"
          placeholder="Select"
          options={Object.entries(RAISED_BY_LABELS).map(([value, label]) => ({ value, label }))}
          error={errors.raised_by?.message}
          {...register("raised_by")}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField
          label="Expected Compl Date"
          type="date"
          error={errors.expected_compl_date?.message}
          {...register("expected_compl_date")}
        />
        <TextField label="Compl Date" type="date" error={errors.compl_date?.message} {...register("compl_date")} />
      </div>

      <TextareaField label="Vessel Remarks" disabled value={defect?.vessel_remarks ?? ""} readOnly />
      <TextareaField label="Shore Remarks" error={errors.shore_remarks?.message} {...register("shore_remarks")} />

      <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <Button type="button" variant="secondary" onClick={onCancel}>
          Cancel
        </Button>
        <Button type="submit" variant="success" isLoading={isSubmitting}>
          Save
        </Button>
      </div>
    </form>
  );
}
