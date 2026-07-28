import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildExternalAuditSchema, type ExternalAuditFormValues } from "./externalAuditSchema";
import { externalAuditService } from "./externalAuditService";
import type { ExternalAuditDetail, ExternalAuditOptions } from "./externalAudit";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface ExternalAuditFormProps {
  externalAudit?: ExternalAuditDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: ExternalAuditOptions = {
  vessels: [],
};

const TYPE_OF_AUDIT_OPTIONS = [
  { value: "ISM", label: "ISM" },
  { value: "ISPS", label: "ISPS" },
  { value: "MLC", label: "MLC" },
  { value: "ISM/ISPS/MLC", label: "ISM/ISPS/MLC" },
];

function emptyValues(): ExternalAuditFormValues {
  return {
    ref_no: "",
    vessel_id: null,
    department: "",
    dateof_audit: new Date().toISOString().slice(0, 10),
    portof_audit: "",
    typeof_audit: null,
    master_name: "",
    chief_engineer: "",
    auditor_name: "",
    shore_remarks: "",
  };
}

function detailToFormValues(r: ExternalAuditDetail): ExternalAuditFormValues {
  return {
    ...emptyValues(),
    ref_no: r.ref_no,
    vessel_id: r.vessel_id,
    department: r.department ?? "",
    dateof_audit: r.dateof_audit,
    portof_audit: r.portof_audit ?? "",
    typeof_audit: r.typeof_audit,
    master_name: r.master_name ?? "",
    chief_engineer: r.chief_engineer ?? "",
    auditor_name: r.auditor_name ?? "",
    shore_remarks: r.shore_remarks ?? "",
  };
}

/** Ported from admin/externalaudit/add_external.php. */
export function ExternalAuditForm({ externalAudit, onSuccess, onCancel }: ExternalAuditFormProps) {
  const isCreate = !externalAudit;
  const [options, setOptions] = useState<ExternalAuditOptions>(emptyOptions);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ExternalAuditFormValues>({
    resolver: zodResolver(buildExternalAuditSchema(isCreate)),
    defaultValues: externalAudit ? detailToFormValues(externalAudit) : emptyValues(),
  });

  useEffect(() => {
    externalAuditService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    // See CompanyInspectionForm's identical effect: <option> elements
    // built from fetched options don't exist at useForm's mount-time
    // default-value assignment, so re-sync once they've actually rendered.
    if (options.vessels.length > 0) {
      reset(externalAudit ? detailToFormValues(externalAudit) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  const onSubmit = async (values: ExternalAuditFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await externalAuditService.create(values);
      } else {
        await externalAuditService.update(externalAudit.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof ExternalAuditFormValues, { message: messages[0] });
        });
        return;
      }
      setFormError("Something went wrong. Please try again.");
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
      {formError && <Alert variant="error">{formError}</Alert>}

      {!isCreate && externalAudit?.added_by === "VESSEL" && (
        <Alert variant="info">This report was added by the vessel. Vessel remarks are read-only here.</Alert>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Ref. No." error={errors.ref_no?.message} {...register("ref_no")} />
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
        <TextField label="Date of Audit" type="date" error={errors.dateof_audit?.message} {...register("dateof_audit")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Port of Audit" error={errors.portof_audit?.message} {...register("portof_audit")} />
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

      {/* Legacy scopes this to an Address Book category (CLASSIFICATION
          SOCIETY); that module isn't migrated, so it's free text here. */}
      <TextField label="Auditor" error={errors.auditor_name?.message} {...register("auditor_name")} />

      {!isCreate && (
        <TextareaField
          label="Vessel Remarks"
          value={externalAudit?.vessel_remarks ?? ""}
          disabled
          readOnly
        />
      )}
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
