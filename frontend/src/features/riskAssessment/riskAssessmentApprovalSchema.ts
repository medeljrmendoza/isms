import { z } from "zod";

const optionalText = z.string().trim().optional().or(z.literal(""));

/**
 * Mirrors the two "TO BE FILLED OUT BY ... SUPERINTENDENT" sections of
 * add_risk_assessment_v.php — every other field on that form is
 * `disabled` in legacy markup and add_report() only ever writes these
 * two tracks, so this is the entire editable surface of the module.
 * Both sections are always present in the form values; which ones
 * actually get submitted depends on which approval track the report
 * requires (approval_from_shore / approval_from_marine).
 */
export const riskAssessmentApprovalSchema = z.object({
  shore_approved: z.enum(["YES", "NO"]),
  shore_date_approved: optionalText,
  shore_remarks: optionalText,
  marine_approved: z.enum(["YES", "NO"]),
  marine_date_approved: optionalText,
  marine_remarks: optionalText,
});

export type RiskAssessmentApprovalFormValues = z.infer<typeof riskAssessmentApprovalSchema>;
