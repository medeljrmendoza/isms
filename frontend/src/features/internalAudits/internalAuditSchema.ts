import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);
const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalId = z
  .union([z.number(), z.string()])
  .nullable()
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v)));

/**
 * Mirrors InternalAuditRequest (backend) and, before that,
 * admin/internalaudit/add_internal_report.php's bootstrapValidator +
 * submitHandler. Vessel is frozen after creation, so the "select a
 * vessel" rule only applies on create.
 */
export function buildInternalAuditSchema(isCreate: boolean) {
  return z
    .object({
      audit_ref: z
        .string()
        .trim()
        .min(1, "Required field")
        .regex(/^[a-zA-Z0-9_-]+$/, "Only letters, numbers, hyphens (-), and underscores (_) are allowed."),
      vessel_id: optionalId,
      department: optionalText,
      this_date: z.string().trim().min(1, "Required field"),
      placeof_audit: z.string().trim().min(1, "Required field"),
      typeof_audit: z.enum(["ISM", "ISPS", "MLC", "ISM/ISPS/MLC"]).nullable().optional(),
      master_name: optionalText,
      chief_engineer: optionalText,
      auditor_name: optionalText,
      remarks: optionalText,
    })
    .superRefine((data, ctx) => {
      if (isCreate && !data.vessel_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select a vessel." });
      }
      if (data.this_date > today()) {
        ctx.addIssue({ code: "custom", path: ["this_date"], message: "Should not be greater than today." });
      }
    });
}

export type InternalAuditFormValues = z.infer<ReturnType<typeof buildInternalAuditSchema>>;
