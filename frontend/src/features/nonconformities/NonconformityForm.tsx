import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { buildNonconformitySchema, type NonconformityFormValues } from "./nonconformitySchema";
import { nonconformityService } from "./nonconformityService";
import type { NonconformityDetail, NonconformityOption } from "./nonconformity";
import { isApiValidationError } from "../auth/auth";
import { TextField } from "../../components/ui/TextField";
import { TextareaField } from "../../components/ui/TextareaField";
import { SelectField } from "../../components/ui/SelectField";
import { Button } from "../../components/ui/Button";
import { Alert } from "../../components/ui/Alert";

interface NonconformityFormProps {
  nonconformity?: NonconformityDetail;
  vessels: NonconformityOption[];
  chapters: NonconformityOption[];
  onSuccess: () => void;
  onCancel: () => void;
}

const SPECIAL_SOURCES = ["FLAG STATE", "PSC INSPECTION", "COMPANY INSPECTION", "INTERNAL AUDIT", "EXTERNAL AUDIT"];

function emptyValues(): NonconformityFormValues {
  return {
    ncr_no: "",
    date_of_nc: new Date().toISOString().slice(0, 10),
    vessel_company: "",
    vessel_id: "",
    company: "",
    department_name: "",
    reported_by: "",
    reporter_name: "",
    source_of_nc: "OPERATIONAL",
    source_of_nc_others: "",
    source_of_nc_ref_no: "",
    sms_chapterID: "",
    sms_details: "",
    description: "",
    root_cause: "",
    root_cause_incharge: "",
    corrective_action: "",
    corrective_action_incharge: "",
    corrective_action_date: "",
    verification: "",
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
  };
}

function detailToFormValues(d: NonconformityDetail): NonconformityFormValues {
  return {
    ncr_no: d.ncr_no,
    date_of_nc: d.date_of_nc,
    vessel_company: d.vessel_company_raw,
    vessel_id: d.vessel_id ?? "",
    company: d.company ?? "",
    department_name: d.department_name ?? "",
    reported_by: (d.reported_by_raw as "SHORE" | "VESSEL" | null) ?? "",
    reporter_name: d.reporter_name ?? "",
    source_of_nc: d.source_of_nc_raw,
    source_of_nc_others: d.source_of_nc_others ?? "",
    source_of_nc_ref_no: d.source_of_nc_ref_no ?? "",
    sms_chapterID: d.manual_chapter_id ?? "",
    sms_details: d.sms_details ?? "",
    description: d.description,
    root_cause: d.root_cause ?? "",
    root_cause_incharge: d.root_cause_incharge ?? "",
    corrective_action: d.corrective_action ?? "",
    corrective_action_incharge: d.corrective_action_incharge ?? "",
    corrective_action_date: d.corrective_action_date ?? "",
    verification: (d.verification as "COMPLETED" | "FOLLOW-UP" | "ASSISTANCE" | null) ?? "",
    verification_followup: d.verification_followup ?? "",
    verification_assistance: d.verification_assistance ?? "",
    verification_dpa: d.verification_dpa ?? "",
    verification_date: d.verification_date ?? "",
    close_out_completed: d.close_out_completed,
    close_out_followup: d.close_out_followup,
    close_out_followup_nature: d.close_out_followup_nature ?? "",
    close_out_dpa: d.close_out_dpa ?? "",
    close_out_date: d.close_out_date ?? "",
    attach_safety_meeting: d.attach_safety_meeting,
    attach_record_training: d.attach_record_training,
    attach_logbook: d.attach_logbook,
    attach_delivery_note: d.attach_delivery_note,
    attach_photo: d.attach_photo,
    attach_company_forms: d.attach_company_forms,
    attach_others: d.attach_others,
    attach_others_details: d.attach_others_details ?? "",
  };
}

/**
 * Ported from admin/nonconformities/add_nonconformity.php. Not ported:
 * file attachments (no file storage anywhere in this migration — the 7
 * "attached documents" checkboxes below are still real fields, just
 * without the upload control legacy shows alongside them) and the
 * printable header/footer. Vessel/Company and Source of NC are locked
 * (read-only) on edit for special-source records, matching legacy's
 * disabled fields in that state — the backend ignores whatever's
 * submitted for Vessel/Company on edit either way (see
 * NonconformityRepository::legacySave()).
 */
export function NonconformityForm({ nonconformity, vessels, chapters, onSuccess, onCancel }: NonconformityFormProps) {
  const isCreate = !nonconformity;
  const [formError, setFormError] = useState<string | null>(null);
  const sourceLocked = !isCreate && SPECIAL_SOURCES.includes(nonconformity.source_of_nc_raw);

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<NonconformityFormValues>({
    resolver: zodResolver(buildNonconformitySchema(isCreate)),
    defaultValues: nonconformity ? detailToFormValues(nonconformity) : emptyValues(),
  });

  const vesselCompany = watch("vessel_company");
  const sourceOfNc = watch("source_of_nc");
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
        await nonconformityService.update(nonconformity.id, values);
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
        <div className="flex flex-col gap-1">
          <span className="text-sm font-medium text-slate-700">Vessel / Company</span>
          {isCreate ? (
            <>
              <div className="flex gap-4 pt-1">
                <label className="flex items-center gap-1.5 text-sm">
                  <input type="radio" value="VESSEL" {...register("vessel_company")} /> Vessel
                </label>
                <label className="flex items-center gap-1.5 text-sm">
                  <input type="radio" value="COMPANY" {...register("vessel_company")} /> Company
                </label>
              </div>
              {errors.vessel_company && <p className="text-sm text-red-600">{errors.vessel_company.message}</p>}
              {vesselCompany === "VESSEL" && (
                <SelectField
                  label=""
                  className="mt-1"
                  placeholder="Select vessel..."
                  options={vessels.map((v) => ({ value: String(v.id), label: v.label }))}
                  error={errors.vessel_id?.message}
                  {...register("vessel_id")}
                />
              )}
              {vesselCompany === "COMPANY" && (
                <TextField label="" className="mt-1" error={errors.company?.message} {...register("company")} />
              )}
            </>
          ) : (
            <>
              <input type="hidden" {...register("vessel_company")} />
              <input type="hidden" {...register("vessel_id")} />
              <input type="hidden" {...register("company")} />
              <TextField
                label=""
                disabled
                readOnly
                value={
                  nonconformity.vessel_company_raw === "VESSEL"
                    ? (vessels.find((v) => String(v.id) === String(nonconformity.vessel_id))?.label ?? nonconformity.vessel_company)
                    : `Company - ${nonconformity.company ?? ""}`
                }
              />
            </>
          )}
        </div>

        <TextField label="Department Name" error={errors.department_name?.message} {...register("department_name")} />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1">
          <span className="text-sm font-medium text-slate-700">Reporter</span>
          <div className="flex gap-4 pt-1">
            <label className="flex items-center gap-1.5 text-sm">
              <input type="radio" value="SHORE" {...register("reported_by")} /> Shore
            </label>
            <label className="flex items-center gap-1.5 text-sm">
              <input type="radio" value="VESSEL" {...register("reported_by")} /> Vessel
            </label>
          </div>
          {errors.reported_by && <p className="text-sm text-red-600">{errors.reported_by.message}</p>}
        </div>
        <TextField label="Reporter Name" error={errors.reporter_name?.message} {...register("reporter_name")} />
      </div>

      <div className="rounded-md border border-slate-200">
        <div className="border-b border-slate-200 bg-orange-50 px-3 py-1.5 text-sm font-semibold text-slate-700">
          Source of Non Conformance
        </div>
        <div className="flex flex-col gap-3 p-3">
          {sourceLocked ? (
            <>
              <input type="hidden" {...register("source_of_nc")} />
              <TextField label="Source" disabled readOnly value={nonconformity.source_of_nc_raw} />
            </>
          ) : (
            <div className="flex flex-wrap items-center gap-4">
              <label className="flex items-center gap-1.5 text-sm">
                <input type="radio" value="OPERATIONAL" {...register("source_of_nc")} /> Operational
              </label>
              <label className="flex items-center gap-1.5 text-sm">
                <input type="radio" value="OTHERS" {...register("source_of_nc")} /> Other
              </label>
              {sourceOfNc === "OTHERS" && (
                <TextField label="" className="max-w-xs" error={errors.source_of_nc_others?.message} {...register("source_of_nc_others")} />
              )}
            </div>
          )}
          {errors.source_of_nc && <p className="text-sm text-red-600">{errors.source_of_nc.message}</p>}

          <TextField label="Source Ref. No." error={errors.source_of_nc_ref_no?.message} {...register("source_of_nc_ref_no")} />

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <SelectField
              label="SMS Procedure / ISM Code Affected"
              placeholder="Select..."
              options={chapters.map((c) => ({ value: String(c.id), label: c.label }))}
              error={errors.sms_chapterID?.message}
              {...register("sms_chapterID")}
            />
            <TextField label="Details" error={errors.sms_details?.message} {...register("sms_details")} />
          </div>
        </div>
      </div>

      <TextareaField label="Description of Non Conformity" error={errors.description?.message} {...register("description")} />

      <div className="rounded-md border border-slate-200">
        <div className="border-b border-slate-200 bg-orange-50 px-3 py-1.5 text-sm font-semibold text-slate-700">
          Root Cause of Non Conformity
        </div>
        <div className="flex flex-col gap-3 p-3">
          <TextareaField label="" error={errors.root_cause?.message} {...register("root_cause")} />
          <TextField label="Person In-charge" error={errors.root_cause_incharge?.message} {...register("root_cause_incharge")} />
        </div>
      </div>

      <div className="rounded-md border border-slate-200">
        <div className="border-b border-slate-200 bg-orange-50 px-3 py-1.5 text-sm font-semibold text-slate-700">
          Proposed Corrective Action(s)
        </div>
        <div className="flex flex-col gap-3 p-3">
          <TextareaField label="" error={errors.corrective_action?.message} {...register("corrective_action")} />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <TextField
              label="Person In-charge"
              error={errors.corrective_action_incharge?.message}
              {...register("corrective_action_incharge")}
            />
            <TextField
              label="Target Date of Completion"
              type="date"
              error={errors.corrective_action_date?.message}
              {...register("corrective_action_date")}
            />
          </div>
        </div>
      </div>

      <div className="rounded-md border border-slate-200">
        <div className="border-b border-slate-200 bg-orange-50 px-3 py-1.5 text-sm font-semibold text-slate-700">
          Verification of Corrective Action
        </div>
        <div className="flex flex-col gap-2 p-3">
          <label className="flex items-center gap-1.5 text-sm">
            <input type="radio" value="COMPLETED" {...register("verification")} /> Completed per SMS; or
          </label>
          <label className="flex items-center gap-1.5 text-sm">
            <input type="radio" value="FOLLOW-UP" {...register("verification")} /> Follow-up is required as per SMS; or
          </label>
          {verification === "FOLLOW-UP" && (
            <TextareaField label="Nature of Follow-up" error={errors.verification_followup?.message} {...register("verification_followup")} />
          )}
          <label className="flex items-center gap-1.5 text-sm">
            <input type="radio" value="ASSISTANCE" {...register("verification")} /> Assistance is required
          </label>
          {verification === "ASSISTANCE" && (
            <TextareaField
              label="Nature of Required Assistance"
              error={errors.verification_assistance?.message}
              {...register("verification_assistance")}
            />
          )}
          {errors.verification && <p className="text-sm text-red-600">{errors.verification.message}</p>}
          <div>
            <Button type="button" variant="secondary" className="!px-2 !py-1 text-xs" onClick={() => setValue("verification", "")}>
              Remove Selected Option
            </Button>
          </div>

          <div className="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <TextField label="DPA / Safety Management Committee" error={errors.verification_dpa?.message} {...register("verification_dpa")} />
            <TextField label="Verification Date" type="date" error={errors.verification_date?.message} {...register("verification_date")} />
          </div>
        </div>
      </div>

      <div className="rounded-md border border-slate-200">
        <div className="border-b border-slate-200 bg-orange-50 px-3 py-1.5 text-sm font-semibold text-slate-700">Close Out</div>
        <div className="flex flex-col gap-2 p-3">
          <label className="flex items-center gap-1.5 text-sm">
            <input type="checkbox" {...register("close_out_completed")} /> Completed and closed out.
          </label>
          <label className="flex items-center gap-1.5 text-sm">
            <input type="checkbox" {...register("close_out_followup")} /> Follow-up is required as per SMS.
          </label>
          {closeOutFollowup && (
            <TextareaField
              label="Nature of Follow-up"
              error={errors.close_out_followup_nature?.message}
              {...register("close_out_followup_nature")}
            />
          )}
          {errors.close_out_completed && <p className="text-sm text-red-600">{errors.close_out_completed.message}</p>}

          <div className="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <TextField label="Designated Person Ashore" error={errors.close_out_dpa?.message} {...register("close_out_dpa")} />
            <TextField label="Close Out Date" type="date" error={errors.close_out_date?.message} {...register("close_out_date")} />
          </div>
          {!closeOutCompleted && !closeOutFollowup && (errors.close_out_dpa || errors.close_out_date) && (
            <p className="text-sm text-red-600">{errors.close_out_dpa?.message ?? errors.close_out_date?.message}</p>
          )}
        </div>
      </div>

      <div className="rounded-md border border-slate-200">
        <div className="border-b border-slate-200 bg-orange-50 px-3 py-1.5 text-sm font-semibold text-slate-700">Attached Documents</div>
        <div className="grid grid-cols-1 gap-2 p-3 sm:grid-cols-3">
          <label className="flex items-center gap-1.5 text-sm">
            <input type="checkbox" {...register("attach_safety_meeting")} /> Safety Meeting
          </label>
          <label className="flex items-center gap-1.5 text-sm">
            <input type="checkbox" {...register("attach_record_training")} /> Record of Training
          </label>
          <label className="flex items-center gap-1.5 text-sm">
            <input type="checkbox" {...register("attach_logbook")} /> Logbook Entry
          </label>
          <label className="flex items-center gap-1.5 text-sm">
            <input type="checkbox" {...register("attach_delivery_note")} /> Delivery Note
          </label>
          <label className="flex items-center gap-1.5 text-sm">
            <input type="checkbox" {...register("attach_photo")} /> Photo
          </label>
          <label className="flex items-center gap-1.5 text-sm">
            <input type="checkbox" {...register("attach_company_forms")} /> Company Forms
          </label>
          <div className="flex items-center gap-1.5 sm:col-span-3">
            <label className="flex items-center gap-1.5 text-sm">
              <input type="checkbox" {...register("attach_others")} /> Others:
            </label>
            {attachOthers && (
              <TextField label="" className="flex-1" error={errors.attach_others_details?.message} {...register("attach_others_details")} />
            )}
          </div>
        </div>
      </div>

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
