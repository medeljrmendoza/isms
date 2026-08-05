import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildInternalAuditSchema, type InternalAuditFormValues } from "./internalAuditSchema";
import { internalAuditService } from "./internalAuditService";
import type { InternalAuditDetail, InternalAuditOptions } from "./internalAudit";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface InternalAuditFormProps {
  internalAudit?: InternalAuditDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: InternalAuditOptions = {
  vessels: [],
};

const TYPE_OF_AUDIT_OPTIONS = [
  { value: "ISM", label: "ISM" },
  { value: "ISPS", label: "ISPS" },
  { value: "MLC", label: "MLC" },
  { value: "ISM/ISPS/MLC", label: "ISM/ISPS/MLC" },
];

function emptyValues(): InternalAuditFormValues {
  return {
    audit_ref: "",
    vessel_id: null,
    department: "",
    this_date: new Date().toISOString().slice(0, 10),
    placeof_audit: "",
    typeof_audit: null,
    master_name: "",
    chief_engineer: "",
    auditor_name: "",
    remarks: "",
  };
}

function detailToFormValues(r: InternalAuditDetail): InternalAuditFormValues {
  return {
    ...emptyValues(),
    audit_ref: r.audit_ref,
    vessel_id: r.vessel_id,
    department: r.department ?? "",
    this_date: r.this_date,
    placeof_audit: r.placeof_audit ?? "",
    typeof_audit: r.typeof_audit,
    master_name: r.master_name ?? "",
    chief_engineer: r.chief_engineer ?? "",
    auditor_name: r.auditor_name ?? "",
    remarks: r.remarks ?? "",
  };
}

/** Ported from admin/internalaudit/add_internal_report.php. */
export function InternalAuditForm({ internalAudit, onSuccess, onCancel }: InternalAuditFormProps) {
  const isCreate = !internalAudit;
  const [options, setOptions] = useState<InternalAuditOptions>(emptyOptions);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<InternalAuditFormValues>({
    resolver: zodResolver(buildInternalAuditSchema(isCreate)),
    defaultValues: internalAudit ? detailToFormValues(internalAudit) : emptyValues(),
  });

  useEffect(() => {
    internalAuditService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    // See CompanyInspectionForm's identical effect: <option> elements
    // built from fetched options don't exist at useForm's mount-time
    // default-value assignment, so re-sync once they've actually rendered.
    if (options.vessels.length > 0) {
      reset(internalAudit ? detailToFormValues(internalAudit) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  const onSubmit = async (values: InternalAuditFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await internalAuditService.create(values);
      } else {
        // internalAudit.id is always numeric here: the edit form only opens for can_edit rows (local-only).
        await internalAuditService.update(internalAudit.id as number, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof InternalAuditFormValues, { message: messages[0] });
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
        <TextField label="Ref. No." error={errors.audit_ref?.message} {...register("audit_ref")} />
        <TextField label="Department" error={errors.department?.message} {...register("department")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {/* Frozen after creation (backend ignores vessel_id on update) —
            styled read-only via pointer-events rather than the native
            `disabled` attribute, since a disabled <select> doesn't
            reliably pick up its react-hook-form default value on mount. */}
        <div className={!isCreate ? "pointer-events-none opacity-60" : undefined}>
          <SelectField
            label="Vessel"
            placeholder="Select vessel..."
            options={options.vessels.map((v) => ({ value: String(v.id), label: v.label }))}
            error={errors.vessel_id?.message}
            tabIndex={!isCreate ? -1 : undefined}
            {...register("vessel_id", { setValueAs: (v) => (v ? Number(v) : null) })}
          />
        </div>
        <TextField label="Date of Audit" type="date" error={errors.this_date?.message} {...register("this_date")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Port of Audit" error={errors.placeof_audit?.message} {...register("placeof_audit")} />
        <SelectField
          label="Type of Audit"
          placeholder="Select Type of Audit"
          options={TYPE_OF_AUDIT_OPTIONS}
          error={errors.typeof_audit?.message}
          {...register("typeof_audit", { setValueAs: (v) => (v ? v : null) })}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Master" error={errors.master_name?.message} {...register("master_name")} />
        <TextField label="Chief Engineer" error={errors.chief_engineer?.message} {...register("chief_engineer")} />
      </div>

      {/* Legacy uses a plain text field here, not an Address Book FK
          (unlike Company Inspection's inspector). */}
      <TextField label="Auditor" error={errors.auditor_name?.message} {...register("auditor_name")} />

      <TextareaField label="Remarks" {...register("remarks")} />

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
