import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildFlagStateReportSchema, type FlagStateReportFormValues } from "./flagStateReportSchema";
import { flagStateReportService } from "./flagStateReportService";
import type { FlagStateReportDetail, FlagStateReportOptions } from "./flagState";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface FlagStateReportFormProps {
  flagStateReport?: FlagStateReportDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: FlagStateReportOptions = {
  vessels: [],
};

function emptyValues(): FlagStateReportFormValues {
  return {
    ref_no: "",
    vessel_id: null,
    dateof_inspection: new Date().toISOString().slice(0, 10),
    placeof_inspection: "",
    inspector: "",
    flag_cost: null,
    shore_remarks: "",
  };
}

function detailToFormValues(r: FlagStateReportDetail): FlagStateReportFormValues {
  return {
    ...emptyValues(),
    ref_no: r.ref_no,
    vessel_id: r.vessel_id,
    dateof_inspection: r.dateof_inspection,
    placeof_inspection: r.placeof_inspection ?? "",
    inspector: r.inspector ?? "",
    flag_cost: r.flag_cost ? Number(r.flag_cost) : null,
    shore_remarks: r.shore_remarks ?? "",
  };
}

/** Ported from admin/flagstate/add_flag_state.php. */
export function FlagStateReportForm({ flagStateReport, onSuccess, onCancel }: FlagStateReportFormProps) {
  const isCreate = !flagStateReport;
  const [options, setOptions] = useState<FlagStateReportOptions>(emptyOptions);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FlagStateReportFormValues>({
    resolver: zodResolver(buildFlagStateReportSchema(isCreate)),
    defaultValues: flagStateReport ? detailToFormValues(flagStateReport) : emptyValues(),
  });

  useEffect(() => {
    flagStateReportService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    // See ExternalAuditForm's identical effect: <option> elements built
    // from fetched options don't exist at useForm's mount-time
    // default-value assignment, so re-sync once they've actually rendered.
    if (options.vessels.length > 0) {
      reset(flagStateReport ? detailToFormValues(flagStateReport) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  const onSubmit = async (values: FlagStateReportFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await flagStateReportService.create(values);
      } else {
        await flagStateReportService.update(flagStateReport.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof FlagStateReportFormValues, { message: messages[0] });
        });
        return;
      }
      setFormError("Something went wrong. Please try again.");
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-5">
      {formError && <Alert variant="error">{formError}</Alert>}

      {!isCreate && flagStateReport?.added_by === "VESSEL" && (
        <Alert variant="info">This report was added by the vessel. Vessel remarks are read-only here.</Alert>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Ref. No." error={errors.ref_no?.message} {...register("ref_no")} />

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
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Date of Inspection" type="date" error={errors.dateof_inspection?.message} {...register("dateof_inspection")} />
        <TextField label="Place of Inspection" error={errors.placeof_inspection?.message} {...register("placeof_inspection")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Inspector" error={errors.inspector?.message} {...register("inspector")} />
        <TextField label="Cost" type="number" step="0.01" error={errors.flag_cost?.message} {...register("flag_cost")} />
      </div>

      {!isCreate && (
        <TextareaField
          label="Vessel Remarks"
          value={flagStateReport?.vessel_remarks ?? ""}
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
