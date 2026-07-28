import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);
const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalId = z
  .union([z.number(), z.string()])
  .nullable()
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v)));

/**
 * Mirrors CompanyInspectionRequest (backend) and, before that,
 * admin/companyinspection/add_company_report.php's bootstrapValidator +
 * submitHandler. Vessel attribution is frozen after creation, so the
 * "select a vessel" rule only applies on create — but the company name
 * stays required on both, matching legacy's edit branch which
 * deliberately re-reads it from the payload.
 */
export function buildCompanyInspectionSchema(isCreate: boolean) {
  return z
    .object({
      audit_ref: z.string().trim().min(1, "Required field"),
      vessel_company: z.enum(["VESSEL", "COMPANY"], { message: "Required field" }),
      vessel_id: optionalId,
      company: optionalText,
      department: optionalText,
      this_date: z.string().trim().min(1, "Required field"),
      placeof_audit: z.string().trim().min(1, "Required field"),
      audit_type_id: optionalId,
      audit_kind_id: optionalId,
      inspector_name: optionalText,
      master_name: optionalText,
      chief_engineer: optionalText,
      remarks: optionalText,
    })
    .superRefine((data, ctx) => {
      if (data.this_date > today()) {
        ctx.addIssue({ code: "custom", path: ["this_date"], message: "Should not be greater than today." });
      }

      if (isCreate && data.vessel_company === "VESSEL" && !data.vessel_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select a vessel." });
      }

      if (data.vessel_company === "COMPANY" && !data.company) {
        ctx.addIssue({ code: "custom", path: ["company"], message: "Please input the company." });
      }
    });
}

export type CompanyInspectionFormValues = z.infer<ReturnType<typeof buildCompanyInspectionSchema>>;
