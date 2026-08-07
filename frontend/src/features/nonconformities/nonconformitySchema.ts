import { z } from "zod";

const optionalText = z.string().trim().optional().or(z.literal(""));

/**
 * Mirrors NonconformityRequest (backend), ported from add_nonconformity.php's
 * bootstrapValidator + submitHandler pairing checks: Corrective Action/Target
 * Date, and the Verification/Close Out DPA+Date pairs, must be all filled or
 * all empty. vessel_id is required only on create — the legacy edit form
 * locks Vessel/Company, and the backend discards whatever it's submitted
 * with there anyway (see NonconformityRepository::legacySave()). source_of_nc
 * is restricted to OPERATIONAL/OTHERS on create, matching the two radios the
 * Add form actually renders.
 */
export function buildNonconformitySchema(isCreate: boolean) {
  return z
    .object({
      ncr_no: z.string().trim().min(1, "Required Field"),
      date_of_nc: z.string().trim().min(1, "Required Field"),
      vessel_company: z.union([z.literal("VESSEL"), z.literal("COMPANY"), z.literal("")]),
      vessel_id: optionalText,
      company: optionalText,
      department_name: optionalText,
      reported_by: z.union([z.literal("SHORE"), z.literal("VESSEL"), z.literal("")]),
      reporter_name: optionalText,
      source_of_nc: z.string().trim().min(1, "Required Field"),
      source_of_nc_others: optionalText,
      source_of_nc_ref_no: optionalText,
      sms_chapterID: optionalText,
      sms_details: optionalText,
      description: z.string().trim().min(1, "Required Field"),
      root_cause: optionalText,
      root_cause_incharge: optionalText,
      corrective_action: optionalText,
      corrective_action_incharge: optionalText,
      corrective_action_date: optionalText,
      verification: z.union([z.literal("COMPLETED"), z.literal("FOLLOW-UP"), z.literal("ASSISTANCE"), z.literal("")]).optional(),
      verification_followup: optionalText,
      verification_assistance: optionalText,
      verification_dpa: optionalText,
      verification_date: optionalText,
      close_out_completed: z.boolean().default(false),
      close_out_followup: z.boolean().default(false),
      close_out_followup_nature: optionalText,
      close_out_dpa: optionalText,
      close_out_date: optionalText,
      attach_safety_meeting: z.boolean().default(false),
      attach_record_training: z.boolean().default(false),
      attach_logbook: z.boolean().default(false),
      attach_delivery_note: z.boolean().default(false),
      attach_photo: z.boolean().default(false),
      attach_company_forms: z.boolean().default(false),
      attach_others: z.boolean().default(false),
      attach_others_details: optionalText,
    })
    .superRefine((values, ctx) => {
      if (!values.vessel_company) {
        ctx.addIssue({ code: "custom", path: ["vessel_company"], message: "Required Field" });
      }
      if (!values.reported_by) {
        ctx.addIssue({ code: "custom", path: ["reported_by"], message: "Required Field" });
      }
      if (isCreate && values.vessel_company === "VESSEL" && !values.vessel_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select Vessel" });
      }
      if (values.vessel_company === "COMPANY" && !values.company) {
        ctx.addIssue({ code: "custom", path: ["company"], message: "Please input Company" });
      }
      if (values.source_of_nc === "OTHERS" && !values.source_of_nc_others) {
        ctx.addIssue({ code: "custom", path: ["source_of_nc_others"], message: "Please input Other Source of Non Compliance" });
      }

      const hasCorrectiveAction = Boolean(values.corrective_action);
      const hasCorrectiveDate = Boolean(values.corrective_action_date);
      if (hasCorrectiveAction && !hasCorrectiveDate) {
        ctx.addIssue({ code: "custom", path: ["corrective_action_date"], message: "Please input Target Date of Completion" });
      }
      if (hasCorrectiveDate && !hasCorrectiveAction) {
        ctx.addIssue({ code: "custom", path: ["corrective_action"], message: "Please input Proposed Corrective Action" });
      }

      const hasVerification = Boolean(values.verification);
      const hasVerificationDpa = Boolean(values.verification_dpa);
      const hasVerificationDate = Boolean(values.verification_date);
      if (hasVerification && (!hasVerificationDpa || !hasVerificationDate)) {
        ctx.addIssue({ code: "custom", path: ["verification_dpa"], message: "Please input DPA and Verification Date" });
      }
      if (!hasVerification && (hasVerificationDpa || hasVerificationDate)) {
        ctx.addIssue({ code: "custom", path: ["verification"], message: "Please select Verification of Corrective Action" });
      }

      const hasCloseOut = values.close_out_completed || values.close_out_followup;
      const hasCloseOutDpa = Boolean(values.close_out_dpa);
      const hasCloseOutDate = Boolean(values.close_out_date);
      if (hasCloseOut && (!hasCloseOutDpa || !hasCloseOutDate)) {
        ctx.addIssue({ code: "custom", path: ["close_out_dpa"], message: "Please input Designated Person Ashore and Close Out Date" });
      }
      if (!hasCloseOut && (hasCloseOutDpa || hasCloseOutDate)) {
        ctx.addIssue({ code: "custom", path: ["close_out_completed"], message: "Please select on Close Out" });
      }
    });
}

export type NonconformityFormValues = z.infer<ReturnType<typeof buildNonconformitySchema>>;
