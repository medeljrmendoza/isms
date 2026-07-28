import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildSireReportSchema, type SireReportFormValues } from "./sireReportSchema";
import { sireReportService } from "./sireReportService";
import type { SireReportDetail, SireReportOptions } from "./sire";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface SireReportFormProps {
  sireReport?: SireReportDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: SireReportOptions = {
  vessels: [],
};

const PASS_FAIL_OPTIONS = [
  { value: "PASS", label: "PASS" },
  { value: "FAIL", label: "FAIL" },
  { value: "N/A", label: "N/A" },
];

function emptyValues(): SireReportFormValues {
  return {
    vessel_id: null,
    dateof_inspection: new Date().toISOString().slice(0, 10),
    placeof_inspection: "",
    company_name: "",
    inspector_name: "",
    sire_cost: null,
    pass_fail: null,
    shore_remarks: "",
  };
}

function detailToFormValues(r: SireReportDetail): SireReportFormValues {
  return {
    ...emptyValues(),
    vessel_id: r.vessel_id,
    dateof_inspection: r.dateof_inspection,
    placeof_inspection: r.placeof_inspection ?? "",
    company_name: r.company_name ?? "",
    inspector_name: r.inspector_name ?? "",
    sire_cost: r.sire_cost ? Number(r.sire_cost) : null,
    pass_fail: r.pass_fail,
    shore_remarks: r.shore_remarks ?? "",
  };
}

/** Ported from admin/sire/add_sire.php. */
export function SireReportForm({ sireReport, onSuccess, onCancel }: SireReportFormProps) {
  const isCreate = !sireReport;
  const [options, setOptions] = useState<SireReportOptions>(emptyOptions);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<SireReportFormValues>({
    resolver: zodResolver(buildSireReportSchema(isCreate)),
    defaultValues: sireReport ? detailToFormValues(sireReport) : emptyValues(),
  });

  useEffect(() => {
    sireReportService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    // See ExternalAuditForm's identical effect: <option> elements built
    // from fetched options don't exist at useForm's mount-time
    // default-value assignment, so re-sync once they've actually rendered.
    if (options.vessels.length > 0) {
      reset(sireReport ? detailToFormValues(sireReport) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  const onSubmit = async (values: SireReportFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await sireReportService.create(values);
      } else {
        await sireReportService.update(sireReport.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof SireReportFormValues, { message: messages[0] });
        });
        return;
      }
      setFormError("Something went wrong. Please try again.");
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
      {formError && <Alert variant="error">{formError}</Alert>}

      {!isCreate && sireReport?.added_by === "VESSEL" && (
        <Alert variant="info">This report was added by the vessel. Vessel remarks are read-only here.</Alert>
      )}

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
        <TextField label="Date of Inspection" type="date" error={errors.dateof_inspection?.message} {...register("dateof_inspection")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Place of Inspection" error={errors.placeof_inspection?.message} {...register("placeof_inspection")} />
        <SelectField
          label="Pass/Fail"
          placeholder="Select..."
          options={PASS_FAIL_OPTIONS}
          error={errors.pass_fail?.message}
          {...register("pass_fail", { setValueAs: (v) => (v ? v : null) })}
        />
      </div>

      {/* Legacy scopes these to Address Book categories (OIL MAJORS
          (SIRE) / SIRE INSPECTORS); that module isn't migrated, so
          they're free text here. */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Company" error={errors.company_name?.message} {...register("company_name")} />
        <TextField label="Inspector" error={errors.inspector_name?.message} {...register("inspector_name")} />
      </div>

      <TextField label="SIRE Cost" type="number" step="0.01" error={errors.sire_cost?.message} {...register("sire_cost")} />

      {!isCreate && (
        <TextareaField
          label="Vessel Remarks"
          value={sireReport?.vessel_remarks ?? ""}
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
