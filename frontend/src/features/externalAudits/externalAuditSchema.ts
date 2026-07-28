import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);
const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalId = z
  .union([z.number(), z.string()])
  .nullable()
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v)));

/**
 * Mirrors ExternalAuditRequest (backend) and, before that,
 * admin/externalaudit/add_external.php's bootstrapValidator +
 * submitHandler. Vessel is frozen after creation, so the "select a
 * vessel" rule only applies on create. vessel_remarks is excluded —
 * legacy renders it read-only in this admin form.
 */
export function buildExternalAuditSchema(isCreate: boolean) {
  return z
    .object({
      ref_no: z.string().trim().min(1, "Required field"),
      vessel_id: optionalId,
      department: optionalText,
      dateof_audit: z.string().trim().min(1, "Required field"),
      portof_audit: z.string().trim().min(1, "Required field"),
      typeof_audit: z.enum(["ISM", "ISPS", "MLC", "ISM/ISPS/MLC"]).nullable().optional(),
      master_name: optionalText,
      chief_engineer: optionalText,
      auditor_name: optionalText,
      shore_remarks: optionalText,
    })
    .superRefine((data, ctx) => {
      if (isCreate && !data.vessel_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select a vessel." });
      }
      if (data.dateof_audit > today()) {
        ctx.addIssue({ code: "custom", path: ["dateof_audit"], message: "Should not be greater than today." });
      }
    });
}

export type ExternalAuditFormValues = z.infer<ReturnType<typeof buildExternalAuditSchema>>;
