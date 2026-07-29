import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildExposureHoursRecordSchema, type ExposureHoursRecordFormValues } from "./exposureHoursRecordSchema";
import { exposureHoursService } from "./exposureHoursService";
import type { ExposureHoursRecordDetail } from "./exposureHours";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface ExposureHoursRecordFormProps {
  vesselId: number;
  record?: ExposureHoursRecordDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

function emptyValues(vesselId: number): ExposureHoursRecordFormValues {
  return {
    vessel_id: vesselId,
    date_from: "",
    date_to: "",
    no_of_crew: 0,
    no_of_fat: 0,
    no_of_ptd: 0,
    no_of_ppd: 0,
    no_of_lwc: 0,
    no_of_rwc: 0,
    no_of_mtc: 0,
    shore_remarks: "",
  };
}

function detailToFormValues(r: ExposureHoursRecordDetail): ExposureHoursRecordFormValues {
  return {
    vessel_id: r.vessel_id,
    date_from: r.date_from,
    date_to: r.date_to,
    no_of_crew: r.no_of_crew,
    no_of_fat: r.no_of_fat,
    no_of_ptd: r.no_of_ptd,
    no_of_ppd: r.no_of_ppd,
    no_of_lwc: r.no_of_lwc,
    no_of_rwc: r.no_of_rwc,
    no_of_mtc: r.no_of_mtc,
    shore_remarks: r.shore_remarks ?? "",
  };
}

/** Ported from admin/exposurehours/add_record.php. */
export function ExposureHoursRecordForm({ vesselId, record, onSuccess, onCancel }: ExposureHoursRecordFormProps) {
  const isCreate = !record;
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ExposureHoursRecordFormValues>({
    resolver: zodResolver(buildExposureHoursRecordSchema(isCreate)),
    defaultValues: record ? detailToFormValues(record) : emptyValues(vesselId),
  });

  const onSubmit = async (values: ExposureHoursRecordFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await exposureHoursService.create(values);
      } else {
        await exposureHoursService.update(record.id, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof ExposureHoursRecordFormValues, { message: messages[0] });
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
        <TextField label="Date From" type="date" error={errors.date_from?.message} {...register("date_from")} />
        <TextField label="Date To" type="date" error={errors.date_to?.message} {...register("date_to")} />
      </div>

      <TextField label="# of Crew" type="number" error={errors.no_of_crew?.message} {...register("no_of_crew")} />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Fatality (FAT)" type="number" error={errors.no_of_fat?.message} {...register("no_of_fat")} />
        <TextField label="Permanent Total Disability (PTD)" type="number" error={errors.no_of_ptd?.message} {...register("no_of_ptd")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Permanent Partial Disability (PPD)" type="number" error={errors.no_of_ppd?.message} {...register("no_of_ppd")} />
        <TextField label="Lost Workday Case (LWC)" type="number" error={errors.no_of_lwc?.message} {...register("no_of_lwc")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Restricted Work Case (RWC)" type="number" error={errors.no_of_rwc?.message} {...register("no_of_rwc")} />
        <TextField label="Medical Treatment Case (MTC)" type="number" error={errors.no_of_mtc?.message} {...register("no_of_mtc")} />
      </div>

      {!isCreate && <TextareaField label="Vessel Remarks" value={record?.vessel_remarks ?? ""} disabled readOnly />}
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
