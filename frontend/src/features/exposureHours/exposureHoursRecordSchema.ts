import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);
const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalId = z
  .union([z.number(), z.string()])
  .nullable()
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v)));
const count = z
  .union([z.number(), z.string()])
  .optional()
  .transform((v) => (v === undefined || v === "" ? 0 : Number(v)));

/**
 * Mirrors ExposureHoursRecordRequest (backend) and, before that,
 * admin/exposurehours/add_record.php's submitHandler: date from/to
 * required and not in the future, date from must not be after date to,
 * # of crew required. The "must not exceed vessel's max crew" and
 * "must not overlap an existing period" rules are enforced server-side
 * and surfaced as field errors on submit — both depend on data (the
 * vessel's max crew, every other record's date range) this form
 * shouldn't have to fetch and duplicate client-side.
 */
export function buildExposureHoursRecordSchema(isCreate: boolean) {
  return z
    .object({
      vessel_id: optionalId,
      date_from: z.string().trim().min(1, "Required field"),
      date_to: z.string().trim().min(1, "Required field"),
      no_of_crew: z.union([z.number(), z.string()]).transform((v) => Number(v)),
      no_of_fat: count,
      no_of_ptd: count,
      no_of_ppd: count,
      no_of_lwc: count,
      no_of_rwc: count,
      no_of_mtc: count,
      shore_remarks: optionalText,
    })
    .superRefine((data, ctx) => {
      if (isCreate && !data.vessel_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select a vessel." });
      }
      if (data.date_from > today()) {
        ctx.addIssue({ code: "custom", path: ["date_from"], message: "Should not be greater than today." });
      }
      if (data.date_to > today()) {
        ctx.addIssue({ code: "custom", path: ["date_to"], message: "Should not be greater than today." });
      }
      if (data.date_from > data.date_to) {
        ctx.addIssue({ code: "custom", path: ["date_from"], message: "Should not be greater than Date To." });
      }
    });
}

export type ExposureHoursRecordFormValues = z.infer<ReturnType<typeof buildExposureHoursRecordSchema>>;
