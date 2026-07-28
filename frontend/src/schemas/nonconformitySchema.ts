import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);

const optionalText = z.string().trim().optional().or(z.literal(""));

/**
 * Mirrors the conditional checks in NonconformityRequest (backend) and,
 * before that, admin/nonconformities/add_nonconformity.php's submitHandler.
 * Kept as one schema for both create and edit — `vessel_id` is only
 * required when creating (vessel/company attribution is frozen after
 * that, same as the backend).
 */
export function buildNonconformitySchema(isCreate: boolean) {
  return z
    .object({
      ncr_no: z.string().trim().min(1, "Required field"),
      date_of_nc: z.string().trim().min(1, "Required field"),
      vessel_company: z.enum(["VESSEL", "COMPANY"], { message: "Required field" }),
      vessel_id: z.number().nullable().optional(),
      company: optionalText,
      department_name: optionalText,
      reported_by: z.enum(["SHORE", "VESSEL"], { message: "Required field" }),
      reporter_name: optionalText,
      source_of_nc: z.enum(["OPERATIONAL", "OTHERS"], { message: "Required field" }),
      source_of_nc_others: optionalText,
      source_of_nc_ref_no: optionalText,
      manual_chapter_id: z.number().nullable().optional(),
      sms_details: optionalText,
      description: z.string().trim().min(1, "Required field"),
      root_cause: optionalText,
      root_cause_incharge: optionalText,
      corrective_action: optionalText,
      corrective_action_incharge: optionalText,
      corrective_action_date: optionalText,
      verification: z.enum(["COMPLETED", "FOLLOW-UP", "ASSISTANCE"]).nullable().optional(),
      verification_followup: optionalText,
      verification_assistance: optionalText,
      verification_dpa: optionalText,
      verification_date: optionalText,
      close_out_completed: z.boolean().optional(),
      close_out_followup: z.boolean().optional(),
      close_out_followup_nature: optionalText,
      close_out_dpa: optionalText,
      close_out_date: optionalText,
      attach_safety_meeting: z.boolean().optional(),
      attach_record_training: z.boolean().optional(),
      attach_logbook: z.boolean().optional(),
      attach_delivery_note: z.boolean().optional(),
      attach_photo: z.boolean().optional(),
      attach_company_forms: z.boolean().optional(),
      attach_others: z.boolean().optional(),
      attach_others_details: optionalText,
    })
    .superRefine((data, ctx) => {
      const todayStr = today();

      if (data.date_of_nc > todayStr) {
        ctx.addIssue({ code: "custom", path: ["date_of_nc"], message: "Date of NC should not be greater than today." });
      }

      if (isCreate && data.vessel_company === "VESSEL" && !data.vessel_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select a vessel." });
      }
      if (data.vessel_company === "COMPANY" && !data.company) {
        ctx.addIssue({ code: "custom", path: ["company"], message: "Please input a company." });
      }
      if (data.source_of_nc === "OTHERS" && !data.source_of_nc_others) {
        ctx.addIssue({ code: "custom", path: ["source_of_nc_others"], message: "Please input the other source." });
      }

      const hasCorrectiveAction = Boolean(data.corrective_action);
      const hasCorrectiveActionDate = Boolean(data.corrective_action_date);
      if (hasCorrectiveAction && !hasCorrectiveActionDate) {
        ctx.addIssue({ code: "custom", path: ["corrective_action_date"], message: "Please input Target Date of Completion." });
      }
      if (!hasCorrectiveAction && hasCorrectiveActionDate) {
        ctx.addIssue({ code: "custom", path: ["corrective_action"], message: "Please input Proposed Corrective Action." });
      }
      if (hasCorrectiveActionDate && data.corrective_action_date! < data.date_of_nc) {
        ctx.addIssue({ code: "custom", path: ["corrective_action_date"], message: "Should not be less than Date of NC." });
      }

      const hasVerification = Boolean(data.verification);
      const hasVerificationDpa = Boolean(data.verification_dpa);
      const hasVerificationDate = Boolean(data.verification_date);
      if (!hasVerification) {
        if (hasVerificationDpa) {
          ctx.addIssue({ code: "custom", path: ["verification_dpa"], message: "Please select a Verification option first." });
        }
        if (hasVerificationDate) {
          ctx.addIssue({ code: "custom", path: ["verification_date"], message: "Please select a Verification option first." });
        }
      } else {
        if (!hasVerificationDpa) {
          ctx.addIssue({ code: "custom", path: ["verification_dpa"], message: "Please input DPA / Safety Management Committee." });
        }
        if (!hasVerificationDate) {
          ctx.addIssue({ code: "custom", path: ["verification_date"], message: "Please input Verification Date." });
        }
      }
      if (hasVerificationDate && data.verification_date! > todayStr) {
        ctx.addIssue({ code: "custom", path: ["verification_date"], message: "Should not be greater than today." });
      }

      const closeOutSelected = Boolean(data.close_out_completed) || Boolean(data.close_out_followup);
      const hasCloseOutDpa = Boolean(data.close_out_dpa);
      const hasCloseOutDate = Boolean(data.close_out_date);
      if (closeOutSelected) {
        if (!hasCloseOutDpa) {
          ctx.addIssue({ code: "custom", path: ["close_out_dpa"], message: "Please input Designated Person Ashore." });
        }
        if (!hasCloseOutDate) {
          ctx.addIssue({ code: "custom", path: ["close_out_date"], message: "Please input Close Out Date." });
        }
      } else {
        if (hasCloseOutDpa) {
          ctx.addIssue({ code: "custom", path: ["close_out_dpa"], message: "Please select a Close Out option first." });
        }
        if (hasCloseOutDate) {
          ctx.addIssue({ code: "custom", path: ["close_out_date"], message: "Please select a Close Out option first." });
        }
      }
      if (hasCloseOutDate && data.close_out_date! > todayStr) {
        ctx.addIssue({ code: "custom", path: ["close_out_date"], message: "Should not be greater than today." });
      }
    });
}

export type NonconformityFormValues = z.infer<ReturnType<typeof buildNonconformitySchema>>;
