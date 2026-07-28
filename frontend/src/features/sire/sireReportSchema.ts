import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);
const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalId = z
  .union([z.number(), z.string()])
  .nullable()
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v)));
const optionalNumber = z
  .union([z.number(), z.string()])
  .nullable()
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v)));

/**
 * Mirrors SireReportRequest (backend) and, before that,
 * admin/sire/add_sire.php's submitHandler (bootstrapValidator's own
 * `fields` block is empty there). Vessel is frozen after creation, so
 * the "select a vessel" rule only applies on create. Unlike every other
 * report module, there's no ref_no field at all. vessel_remarks is
 * excluded — legacy renders it read-only in this admin form.
 * placeof_inspection has no required rule, unlike every other module's
 * "place of X" field — that matches legacy exactly.
 */
export function buildSireReportSchema(isCreate: boolean) {
  return z
    .object({
      vessel_id: optionalId,
      dateof_inspection: z.string().trim().min(1, "Required field"),
      placeof_inspection: optionalText,
      company_name: optionalText,
      inspector_name: optionalText,
      sire_cost: optionalNumber,
      pass_fail: z.enum(["PASS", "FAIL", "N/A"]).nullable().optional(),
      shore_remarks: optionalText,
    })
    .superRefine((data, ctx) => {
      if (isCreate && !data.vessel_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select a vessel." });
      }
      if (data.dateof_inspection > today()) {
        ctx.addIssue({ code: "custom", path: ["dateof_inspection"], message: "Should not be greater than today." });
      }
    });
}

export type SireReportFormValues = z.infer<ReturnType<typeof buildSireReportSchema>>;
