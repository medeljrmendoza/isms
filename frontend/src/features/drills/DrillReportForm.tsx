import { useForm, useFieldArray } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { useState } from "react";
import { drillReportSchema, type DrillReportFormValues } from "./drillReportSchema";
import { drillService } from "./drillService";
import type { DrillReportDetail } from "./drill";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface DrillReportFormProps {
  drillReport: DrillReportDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

function detailToFormValues(r: DrillReportDetail): DrillReportFormValues {
  return {
    master_name: r.master_name ?? "",
    drill_date: r.drill_date,
    drill_time_from: r.drill_time_from ?? "",
    drill_position: r.drill_position ?? "",
    drill_details: r.drill_details ?? "",
    drill_deficiencies: r.drill_deficiencies ?? "",
    drill_corrective_action: r.drill_corrective_action ?? "",
    report_date: r.report_date ?? "",
    vessel_remarks: r.vessel_remarks ?? "",
    receipt_date: r.receipt_date ?? "",
    shore_remarks: r.shore_remarks ?? "",
    crew: r.crew.map((c) => ({ crew_name: c.crew_name })),
  };
}

/**
 * Ported from admin/drillreports/add_drill_report.php. Edit-only — legacy
 * never lets shore create a new drill report or pick a different vessel/
 * drill/date-origin, so there's no vessel/drill selection here at all.
 */
export function DrillReportForm({ drillReport, onSuccess, onCancel }: DrillReportFormProps) {
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    control,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<DrillReportFormValues>({
    resolver: zodResolver(drillReportSchema),
    defaultValues: detailToFormValues(drillReport),
  });

  const crewArray = useFieldArray({ control, name: "crew" });

  const onSubmit = async (values: DrillReportFormValues) => {
    setFormError(null);
    try {
      await drillService.update(drillReport.id, values);
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof DrillReportFormValues, { message: messages[0] });
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
        <div className="pointer-events-none opacity-60">
          <TextField label="Vessel" value={drillReport.vessel} disabled readOnly />
        </div>
        <div className="pointer-events-none opacity-60">
          <TextField label="Drill" value={`${drillReport.drill_type ?? ""} — ${drillReport.drill_name}`} disabled readOnly />
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Master's Name" error={errors.master_name?.message} {...register("master_name")} />
        <TextField label="Date" type="date" error={errors.drill_date?.message} {...register("drill_date")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Time" placeholder="e.g. 10:00 AM" error={errors.drill_time_from?.message} {...register("drill_time_from")} />
        <TextField label="Position" error={errors.drill_position?.message} {...register("drill_position")} />
      </div>

      <fieldset className="flex flex-col gap-3 rounded-md border border-slate-200 p-4">
        <legend className="px-1 text-sm font-semibold text-slate-700">Ranks of Crew Participated</legend>
        {errors.crew?.message && <p className="text-sm text-red-600">{errors.crew.message}</p>}
        {crewArray.fields.map((field, index) => (
          <div key={field.id} className="flex items-end gap-2">
            <div className="flex-1">
              <TextField
                label="Name"
                error={errors.crew?.[index]?.crew_name?.message}
                {...register(`crew.${index}.crew_name`)}
              />
            </div>
            <Button type="button" variant="secondary" className="!px-2 !py-2 text-xs text-red-600" onClick={() => crewArray.remove(index)}>
              Remove
            </Button>
          </div>
        ))}
        <Button
          type="button"
          variant="secondary"
          className="self-start !px-3 !py-1.5 text-sm"
          onClick={() => crewArray.append({ crew_name: "" })}
        >
          + Add Crew
        </Button>
      </fieldset>

      <TextareaField label="Details of Drill / Training" error={errors.drill_details?.message} {...register("drill_details")} />
      <TextareaField label="Found Deficiencies" error={errors.drill_deficiencies?.message} {...register("drill_deficiencies")} />
      <TextareaField
        label="Master's Option for Improvement and Corrective Action"
        error={errors.drill_corrective_action?.message}
        {...register("drill_corrective_action")}
      />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Report Date" type="date" error={errors.report_date?.message} {...register("report_date")} />
        <TextField label="Received Date" type="date" error={errors.receipt_date?.message} {...register("receipt_date")} />
      </div>

      <TextareaField label="Vessel Remarks" error={errors.vessel_remarks?.message} {...register("vessel_remarks")} />
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
