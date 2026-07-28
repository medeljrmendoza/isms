import { useEffect, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildPscReportSchema, type PscReportFormValues } from "../../schemas/pscReportSchema";
import { pscReportService } from "../../api/pscReportService";
import type { PscReportDetail, PscReportOptions } from "../../types/pscReport";
import { isApiValidationError } from "../../types/auth";
import { TextField } from "../ui/TextField";
import { TextareaField } from "../ui/TextareaField";
import { SelectField } from "../ui/SelectField";
import { Button } from "../ui/Button";
import { Alert } from "../ui/Alert";

interface PscReportFormProps {
  pscReport?: PscReportDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyOptions: PscReportOptions = {
  vessels: [],
  mou_authorities: [],
};

function emptyValues(): PscReportFormValues {
  return {
    ref_no: "",
    vessel_id: null,
    dateof_inspection: new Date().toISOString().slice(0, 10),
    placeof_inspection: "",
    mou_id: null,
    mou_others: "",
    name_psco: "",
    master_name: "",
    chief_engineer: "",
    is_detained: false,
    detained_date: "",
    detained_time: "",
    is_released: false,
    released_date: "",
    released_time: "",
    closing_date: "",
    remarks: "",
  };
}

function detailToFormValues(r: PscReportDetail): PscReportFormValues {
  return {
    ...emptyValues(),
    ref_no: r.ref_no,
    vessel_id: r.vessel_id,
    dateof_inspection: r.dateof_inspection,
    placeof_inspection: r.placeof_inspection ?? "",
    mou_id: r.mou_id,
    mou_others: r.mou_others ?? "",
    name_psco: r.name_psco ?? "",
    master_name: r.master_name ?? "",
    chief_engineer: r.chief_engineer ?? "",
    is_detained: r.is_detained,
    detained_date: r.detained_date ?? "",
    detained_time: r.detained_time ?? "",
    is_released: r.is_released,
    released_date: r.released_date ?? "",
    released_time: r.released_time ?? "",
    closing_date: r.closing_date ?? "",
    remarks: r.remarks ?? "",
  };
}

/** Ported from admin/psc/add_psc_report.php. */
export function PscReportForm({ pscReport, onSuccess, onCancel }: PscReportFormProps) {
  const isCreate = !pscReport;
  const [options, setOptions] = useState<PscReportOptions>(emptyOptions);
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    control,
    handleSubmit,
    watch,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<PscReportFormValues>({
    resolver: zodResolver(buildPscReportSchema(isCreate)),
    defaultValues: pscReport ? detailToFormValues(pscReport) : emptyValues(),
  });

  useEffect(() => {
    pscReportService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    // See IncidentReportForm's identical effect: <option>/radio elements
    // built from fetched options don't exist at useForm's mount-time
    // default-value assignment, so re-sync once they've actually rendered.
    if (options.vessels.length > 0) {
      reset(pscReport ? detailToFormValues(pscReport) : emptyValues());
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  const mouId = watch("mou_id");
  const isDetained = watch("is_detained");
  const isReleased = watch("is_released");
  const isOtherMou = options.mou_authorities.find((m) => m.id === mouId)?.label === "Others";

  const onSubmit = async (values: PscReportFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await pscReportService.create(values);
      } else {
        await pscReportService.update(pscReport.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof PscReportFormValues, { message: messages[0] });
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
        <TextField label="Reference No." error={errors.ref_no?.message} {...register("ref_no")} />
        {/* Frozen after creation — see IncidentReportForm's vessel field for why
            this uses a CSS-only disable rather than the native `disabled` attribute. */}
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
        <TextField
          label="Date of Inspection"
          type="date"
          error={errors.dateof_inspection?.message}
          {...register("dateof_inspection")}
        />
        <TextField label="Place of Inspection" error={errors.placeof_inspection?.message} {...register("placeof_inspection")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <SelectField
          label="MOU / Authority"
          placeholder="Select authority..."
          options={options.mou_authorities.map((m) => ({ value: String(m.id), label: m.label }))}
          error={errors.mou_id?.message}
          {...register("mou_id", { setValueAs: (v) => (v ? Number(v) : null) })}
        />
        {isOtherMou && <TextField label="Specify Authority" error={errors.mou_others?.message} {...register("mou_others")} />}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <TextField label="Name of Authorized PSCO" error={errors.name_psco?.message} {...register("name_psco")} />
        <TextField label="Master" error={errors.master_name?.message} {...register("master_name")} />
        <TextField label="Chief Engineer" error={errors.chief_engineer?.message} {...register("chief_engineer")} />
      </div>

      <fieldset className="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50/40 p-4">
        <legend className="px-1 text-sm font-semibold text-amber-800">Detention</legend>

        <div className="flex flex-col gap-1">
          <span className="text-sm font-medium text-slate-700">Vessel Detained?</span>
          <Controller
            control={control}
            name="is_detained"
            render={({ field }) => (
              <div className="flex gap-4 text-sm text-slate-700">
                <label className="flex items-center gap-1.5">
                  <input type="radio" checked={field.value === false} onChange={() => field.onChange(false)} onBlur={field.onBlur} /> No
                </label>
                <label className="flex items-center gap-1.5">
                  <input type="radio" checked={field.value === true} onChange={() => field.onChange(true)} onBlur={field.onBlur} /> Yes
                </label>
              </div>
            )}
          />
        </div>

        {isDetained && (
          <div className="flex flex-col gap-3">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <TextField label="Date Detained" type="date" error={errors.detained_date?.message} {...register("detained_date")} />
              <TextField label="Time Detained" type="time" error={errors.detained_time?.message} {...register("detained_time")} />
            </div>

            <div className="flex flex-col gap-1">
              <span className="text-sm font-medium text-slate-700">Vessel Released?</span>
              <Controller
                control={control}
                name="is_released"
                render={({ field }) => (
                  <div className="flex gap-4 text-sm text-slate-700">
                    <label className="flex items-center gap-1.5">
                      <input type="radio" checked={field.value === false} onChange={() => field.onChange(false)} onBlur={field.onBlur} /> No
                    </label>
                    <label className="flex items-center gap-1.5">
                      <input type="radio" checked={field.value === true} onChange={() => field.onChange(true)} onBlur={field.onBlur} /> Yes
                    </label>
                  </div>
                )}
              />
            </div>

            {isReleased && (
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <TextField label="Date Released" type="date" error={errors.released_date?.message} {...register("released_date")} />
                <TextField label="Time Released" type="time" error={errors.released_time?.message} {...register("released_time")} />
              </div>
            )}
          </div>
        )}
      </fieldset>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Closing Date" type="date" error={errors.closing_date?.message} {...register("closing_date")} />
      </div>
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
