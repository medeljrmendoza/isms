import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);
const optionalText = z.string().trim().optional().or(z.literal(""));

/**
 * Mirrors RevisionHistoryRequest (backend) and, before that,
 * add_sms_revision.php's bootstrapValidator (order/revision no./date/
 * reviewed by/approved by notEmpty) plus its submitHandler date bounds
 * (must be after 1975-01-01, not after today). Section has no required
 * marker in the legacy form; reason_revision's validator entry targets
 * a field that's commented out of the form itself, so it stays
 * optional here too.
 */
export function buildRevisionHistorySchema() {
  return z
    .object({
      manual_chapter_id: z.union([z.number(), z.string()]).nullable().optional(),
      manual_document_id: z
        .union([z.number(), z.string()])
        .nullable()
        .optional()
        .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v))),
      arrangement: z.union([z.number(), z.string()]).transform((v) => Number(v)),
      revision_no: z.string().trim().min(1, "Required Field"),
      date_revised: z.string().trim().min(1, "Required Field"),
      section: optionalText,
      reason_revision: optionalText,
      reviewed_by: z.string().trim().min(1, "Required Field"),
      approved_by: z.string().trim().min(1, "Required Field"),
    })
    .superRefine((data, ctx) => {
      if (!data.manual_document_id) {
        ctx.addIssue({ code: "custom", path: ["manual_document_id"], message: "Please select a Procedure." });
      }
      if (data.date_revised && data.date_revised <= "1975-01-01") {
        ctx.addIssue({ code: "custom", path: ["date_revised"], message: "Please input a valid Date of Revision." });
      }
      if (data.date_revised > today()) {
        ctx.addIssue({ code: "custom", path: ["date_revised"], message: "Should not be greater than today." });
      }
    });
}

export type RevisionHistoryFormValues = z.infer<ReturnType<typeof buildRevisionHistorySchema>>;
