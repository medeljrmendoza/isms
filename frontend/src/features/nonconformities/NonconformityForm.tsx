import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildNonconformitySchema, type NonconformityFormValues } from "./nonconformitySchema";
import { nonconformityService } from "./nonconformityService";
import type { NonconformityDetail, NonconformityOptions } from "./nonconformity";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { CheckboxField } from "../../components/ui/CheckboxField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface NonconformityFormProps {
  nonconformity?: NonconformityDetail;
  onSuccess: () => void;
  onCancel: () => void;
}

const emptyValues = (isCreate: boolean): NonconformityFormValues => ({
  ncr_no: "",
  date_of_nc: new Date().toISOString().slice(0, 10),
  vessel_company: "VESSEL",
  vessel_id: null,
  company: "",
  department_name: "",
  reported_by: isCreate ? ("SHORE" as const) : ("SHORE" as const),
  reporter_name: "",
  source_of_nc: "OPERATIONAL",
  source_of_nc_others: "",
  source_of_nc_ref_no: "",
  manual_chapter_id: null,
  sms_details: "",
  description: "",
  root_cause: "",
  root_cause_incharge: "",
  corrective_action: "",
  corrective_action_incharge: "",
  corrective_action_date: "",
  verification: null,
  verification_followup: "",
  verification_assistance: "",
  verification_dpa: "",
  verification_date: "",
  close_out_completed: false,
  close_out_followup: false,
  close_out_followup_nature: "",
  close_out_dpa: "",
  close_out_date: "",
  attach_safety_meeting: false,
  attach_record_training: false,
  attach_logbook: false,
  attach_delivery_note: false,
  attach_photo: false,
  attach_company_forms: false,
  attach_others: false,
  attach_others_details: "",
});

function detailToFormValues(nc: NonconformityDetail): NonconformityFormValues {
  return {
    ncr_no: nc.ncr_no,
    date_of_nc: nc.date_of_nc,
    vessel_company: nc.vessel_company_raw,
    vessel_id: nc.vessel_id,
    company: nc.company ?? "",
    department_name: nc.department_name ?? "",
    reported_by: (nc.reported_by_raw as "SHORE" | "VESSEL") ?? "SHORE",
    reporter_name: nc.reporter_name ?? "",
    source_of_nc: nc.source_of_nc_raw === "OTHERS" ? "OTHERS" : "OPERATIONAL",
    source_of_nc_others: nc.source_of_nc_others ?? "",
    source_of_nc_ref_no: nc.source_of_nc_ref_no ?? "",
    manual_chapter_id: nc.manual_chapter_id,
    sms_details: nc.sms_details ?? "",
    description: nc.description,
    root_cause: nc.root_cause ?? "",
    root_cause_incharge: nc.root_cause_incharge ?? "",
    corrective_action: nc.corrective_action ?? "",
    corrective_action_incharge: nc.corrective_action_incharge ?? "",
    corrective_action_date: nc.corrective_action_date ?? "",
    verification: nc.verification,
    verification_followup: nc.verification_followup ?? "",
    verification_assistance: nc.verification_assistance ?? "",
    verification_dpa: nc.verification_dpa ?? "",
    verification_date: nc.verification_date ?? "",
    close_out_completed: nc.close_out_completed,
    close_out_followup: nc.close_out_followup,
    close_out_followup_nature: nc.close_out_followup_nature ?? "",
    close_out_dpa: nc.close_out_dpa ?? "",
    close_out_date: nc.close_out_date ?? "",
    attach_safety_meeting: nc.attach_safety_meeting,
    attach_record_training: nc.attach_record_training,
    attach_logbook: nc.attach_logbook,
    attach_delivery_note: nc.attach_delivery_note,
    attach_photo: nc.attach_photo,
    attach_company_forms: nc.attach_company_forms,
    attach_others: nc.attach_others,
    attach_others_details: nc.attach_others_details ?? "",
  };
}

/**
 * Ported from admin/nonconformities/add_nonconformity.php. Vessel/company
 * attribution and reporter type are frozen after creation (legacy
 * doesn't accept them back from the edit payload either) — shown
 * disabled rather than hidden so an editor can still see what was set.
 */
export function NonconformityForm({ nonconformity, onSuccess, onCancel }: NonconformityFormProps) {
  const isCreate = !nonconformity;
  const [options, setOptions] = useState<NonconformityOptions>({ vessels: [], manual_chapters: [] });
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    watch,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<NonconformityFormValues>({
    resolver: zodResolver(buildNonconformitySchema(isCreate)),
    defaultValues: nonconformity ? detailToFormValues(nonconformity) : emptyValues(isCreate),
  });

  useEffect(() => {
    nonconformityService.options().then(setOptions).catch(() => undefined);
  }, []);

  useEffect(() => {
    // Selects built from the option lists (vessel, SMS chapter) don't have
    // their <option> elements until the fetch above resolves, so
    // react-hook-form's mount-time default-value assignment has nothing to
    // attach to. Re-sync once options have actually rendered — this effect
    // (unlike doing it inside the fetch's .then) runs after that render
    // commits, so the elements are really there.
    if (options.vessels.length > 0) {
      reset(nonconformity ? detailToFormValues(nonconformity) : emptyValues(isCreate));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [options]);

  const vesselCompany = watch("vessel_company");
  const source = watch("source_of_nc");
  const verification = watch("verification");
  const closeOutCompleted = watch("close_out_completed");
  const closeOutFollowup = watch("close_out_followup");
  const attachOthers = watch("attach_others");

  const onSubmit = async (values: NonconformityFormValues) => {
    setFormError(null);
    try {
      if (isCreate) {
        await nonconformityService.create(values);
      } else {
        // Editing is only ever reachable for local records (can_edit is
        // always false for legacy-sourced rows, so the Edit button that
        // leads here never renders for a legacy string id).
        await nonconformityService.update(nonconformity.id as number, values);
      }
      onSuccess();
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        Object.entries(fieldErrors).forEach(([field, messages]) => {
          setError(field as keyof NonconformityFormValues, { message: messages[0] });
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
        <TextField label="NCR No." error={errors.ncr_no?.message} {...register("ncr_no")} />
        <TextField label="Date of NC" type="date" error={errors.date_of_nc?.message} {...register("date_of_nc")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {/* Frozen after creation (backend ignores vessel_company/vessel_id on
            update) — styled read-only via pointer-events rather than the
            native `disabled` attribute, since disabled radios/selects don't
            reliably pick up their react-hook-form default value on mount. */}
        <div className={`flex flex-col gap-2 ${!isCreate ? "pointer-events-none opacity-60" : ""}`}>
          <span className="text-sm font-medium text-slate-700">Vessel / Company</span>
          <div className="flex gap-4 text-sm text-slate-700">
            <label className="flex items-center gap-1.5">
              <input type="radio" value="VESSEL" tabIndex={!isCreate ? -1 : undefined} {...register("vessel_company")} /> Vessel
            </label>
            <label className="flex items-center gap-1.5">
              <input type="radio" value="COMPANY" tabIndex={!isCreate ? -1 : undefined} {...register("vessel_company")} /> Company
            </label>
          </div>
          {errors.vessel_company && <p className="text-sm text-red-600">{errors.vessel_company.message}</p>}

          {vesselCompany === "VESSEL" ? (
            <SelectField
              label="Vessel"
              placeholder="Select vessel..."
              options={options.vessels.map((v) => ({ value: String(v.id), label: v.label }))}
              error={errors.vessel_id?.message}
              tabIndex={!isCreate ? -1 : undefined}
              {...register("vessel_id", { setValueAs: (v) => (v ? Number(v) : null) })}
            />
          ) : (
            <TextField label="Company" error={errors.company?.message} {...register("company")} />
          )}
        </div>

        <TextField label="Department Name" error={errors.department_name?.message} {...register("department_name")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <span className="text-sm font-medium text-slate-700">Reporter</span>
          <div className="flex gap-4 text-sm text-slate-700">
            <label className="flex items-center gap-1.5">
              <input type="radio" value="SHORE" {...register("reported_by")} /> Shore
            </label>
            <label className="flex items-center gap-1.5">
              <input type="radio" value="VESSEL" {...register("reported_by")} /> Vessel
            </label>
          </div>
          {errors.reported_by && <p className="text-sm text-red-600">{errors.reported_by.message}</p>}
        </div>
        <TextField label="Reporter Name" error={errors.reporter_name?.message} {...register("reporter_name")} />
      </div>

      <fieldset className="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50/40 p-4">
        <legend className="px-1 text-sm font-semibold text-amber-800">Source of Non Conformance</legend>
        <div className="flex flex-wrap items-center gap-4 text-sm text-slate-700">
          <label className="flex items-center gap-1.5">
            <input type="radio" value="OPERATIONAL" {...register("source_of_nc")} /> Operational
          </label>
          <label className="flex items-center gap-1.5">
            <input type="radio" value="OTHERS" {...register("source_of_nc")} /> Other
          </label>
          {source === "OTHERS" && (
            <TextField
              label=""
              placeholder="Specify other source"
              className="min-w-[200px]"
              error={errors.source_of_nc_others?.message}
              {...register("source_of_nc_others")}
            />
          )}
        </div>
        {errors.source_of_nc && <p className="text-sm text-red-600">{errors.source_of_nc.message}</p>}

        <TextField label="Source Ref. No." error={errors.source_of_nc_ref_no?.message} {...register("source_of_nc_ref_no")} />

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <SelectField
            label="SMS Procedure / ISM Code Affected"
            placeholder="Select chapter..."
            options={options.manual_chapters.map((c) => ({ value: String(c.id), label: c.label }))}
            {...register("manual_chapter_id", { setValueAs: (v) => (v ? Number(v) : null) })}
          />
          <TextField label="SMS Details" error={errors.sms_details?.message} {...register("sms_details")} />
        </div>
      </fieldset>

      <TextareaField label="Description of Non Conformity" error={errors.description?.message} {...register("description")} />

      <TextareaField label="Root Cause of Non Conformity" error={errors.root_cause?.message} {...register("root_cause")} />
      <TextField label="Person In-charge (Root Cause)" error={errors.root_cause_incharge?.message} {...register("root_cause_incharge")} />

      <TextareaField label="Proposed Corrective Action(s)" error={errors.corrective_action?.message} {...register("corrective_action")} />
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField label="Person In-charge (Corrective Action)" error={errors.corrective_action_incharge?.message} {...register("corrective_action_incharge")} />
        <TextField label="Target Date of Completion" type="date" error={errors.corrective_action_date?.message} {...register("corrective_action_date")} />
      </div>

      <fieldset className="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50/40 p-4">
        <legend className="px-1 text-sm font-semibold text-amber-800">Verification of Corrective Action</legend>
        <div className="flex flex-col gap-2 text-sm text-slate-700">
          <label className="flex items-center gap-1.5">
            <input type="radio" value="COMPLETED" {...register("verification")} /> Completed per SMS
          </label>
          <label className="flex items-center gap-1.5">
            <input type="radio" value="FOLLOW-UP" {...register("verification")} /> Follow-up is required as per SMS
          </label>
          {verification === "FOLLOW-UP" && (
            <TextareaField label="Nature of Follow-up" error={errors.verification_followup?.message} {...register("verification_followup")} />
          )}
          <label className="flex items-center gap-1.5">
            <input type="radio" value="ASSISTANCE" {...register("verification")} /> Assistance is required
          </label>
          {verification === "ASSISTANCE" && (
            <TextareaField label="Nature of Required Assistance" error={errors.verification_assistance?.message} {...register("verification_assistance")} />
          )}
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <TextField label="DPA / Safety Management Committee" error={errors.verification_dpa?.message} {...register("verification_dpa")} />
          <TextField label="Verification Date" type="date" error={errors.verification_date?.message} {...register("verification_date")} />
        </div>
      </fieldset>

      <fieldset className="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50/40 p-4">
        <legend className="px-1 text-sm font-semibold text-amber-800">Close Out</legend>
        <CheckboxField label="Completed and closed out." {...register("close_out_completed")} />
        <CheckboxField label="Follow-up is required as per SMS." {...register("close_out_followup")} />
        {(closeOutCompleted || closeOutFollowup) && (
          <TextareaField
            label="Nature of Follow-up"
            error={errors.close_out_followup_nature?.message}
            {...register("close_out_followup_nature")}
          />
        )}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <TextField label="Designated Person Ashore" error={errors.close_out_dpa?.message} {...register("close_out_dpa")} />
          <TextField label="Close Out Date" type="date" error={errors.close_out_date?.message} {...register("close_out_date")} />
        </div>
      </fieldset>

      <fieldset className="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50/40 p-4">
        <legend className="px-1 text-sm font-semibold text-amber-800">Attached Documents</legend>
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
          <CheckboxField label="Safety Meeting" {...register("attach_safety_meeting")} />
          <CheckboxField label="Record of Training" {...register("attach_record_training")} />
          <CheckboxField label="Logbook Entry" {...register("attach_logbook")} />
          <CheckboxField label="Delivery Note" {...register("attach_delivery_note")} />
          <CheckboxField label="Photo" {...register("attach_photo")} />
          <CheckboxField label="Company Forms" {...register("attach_company_forms")} />
        </div>
        <CheckboxField label="Others:" {...register("attach_others")} />
        {attachOthers && (
          <TextField label="" placeholder="Details" error={errors.attach_others_details?.message} {...register("attach_others_details")} />
        )}
      </fieldset>

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
