import { z } from "zod";

const optionalText = z.string().trim().optional().or(z.literal(""));

const hazardSchema = z.object({
  unwanted_consequences: z.string().trim().min(1, "Required"),
  underlying_causes: z.string().trim().min(1, "Required"),
  severity: z.coerce.number().int().min(1).max(5),
  likelihood: z.coerce.number().int().min(1).max(5),
  risk: z.string(),
  existing_control: optionalText,
  additional_control: optionalText,
  re_severity: z.coerce.number().int().min(1).max(5).optional(),
  re_likelihood: z.coerce.number().int().min(1).max(5),
  re_risk: z.string(),
});

const personSchema = z.object({
  person_details: z.string().trim().min(1, "Required"),
});

/**
 * Mirrors RiskAssessmentShoreRequest (backend) and, before that,
 * admin/riskassessmentshore/add_risk_assessment_v.php's bootstrapValidator
 * + submitHandler checks. report_type/vessel_id/category/operation are
 * only meaningful on create — the backend freezes them on update, same
 * as legacy's edit branch (never reads them from the edit payload).
 */
export const riskAssessmentShoreSchema = z
  .object({
    report_type: z.enum(["SHORE", "VESSEL"]),
    vessel_id: optionalText,
    report_no: z.string().trim().min(1, "Required field"),
    risk_date: z.string().trim().min(1, "Required field"),
    risk_schedule: z.string().trim().min(1, "Required field"),
    port: z.string().trim().min(1, "Required field"),
    department: z.string().trim().min(1, "Required field"),
    activity: z.enum(["ROUTINE", "NON-ROUTINE"]),
    risk_category_shore_id: optionalText,
    other_category_name: optionalText,
    risk_operation_shore_id: optionalText,
    other_operation_name: optionalText,
    overall_risk: optionalText,
    remarks: optionalText,
    date_closed: optionalText,

    approval_from_shore: z.enum(["YES", "NO"]),
    shore_is_approved: z.enum(["YES", "NO"]).optional(),
    date_approved: optionalText,
    shore_remarks: optionalText,
    approval_from_marine: z.enum(["YES", "NO"]),
    marine_is_approved: z.enum(["YES", "NO"]).optional(),
    marine_date_approved: optionalText,
    marine_remarks: optionalText,

    hazards: z.array(hazardSchema),
    people: z.array(personSchema),
  })
  .superRefine((data, ctx) => {
    if (data.report_type === "VESSEL" && !data.vessel_id) {
      ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select Vessel." });
    }
    if (!data.risk_category_shore_id && !data.other_category_name) {
      ctx.addIssue({ code: "custom", path: ["other_category_name"], message: "Please specify Other Category." });
    }
    if (!data.risk_operation_shore_id && !data.other_operation_name) {
      ctx.addIssue({ code: "custom", path: ["other_operation_name"], message: "Please specify Other Task." });
    }
  });

export type RiskAssessmentShoreFormValues = z.infer<typeof riskAssessmentShoreSchema>;
