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
 * Mirrors FlagStateReportRequest (backend) and, before that,
 * admin/flagstate/add_flag_state.php's bootstrapValidator (ref_no
 * notEmpty) + submitHandler (vessel required on create, date required
 * and not in the future). vessel_remarks is excluded — legacy renders
 * it read-only in this admin form. Unlike SIRE/Non-SIRE, `inspector` is
 * plain free text in legacy too (not an Address Book FK), so no
 * dropped-FK note applies here.
 */
export function buildFlagStateReportSchema(isCreate: boolean) {
  return z
    .object({
      ref_no: z.string().trim().min(1, "Required field"),
      vessel_id: optionalId,
      dateof_inspection: z.string().trim().min(1, "Required field"),
      placeof_inspection: optionalText,
      inspector: optionalText,
      flag_cost: optionalNumber,
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

export type FlagStateReportFormValues = z.infer<ReturnType<typeof buildFlagStateReportSchema>>;
