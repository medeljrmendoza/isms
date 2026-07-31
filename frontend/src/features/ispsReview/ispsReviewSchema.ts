import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);
const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalId = z
  .union([z.number(), z.string()])
  .nullable()
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v)));

const presentSchema = z.object({
  name: z.string().trim().min(1, "Required"),
  position: optionalText,
});

/**
 * Mirrors IspsReviewRequest (backend) and, before that,
 * add_isps_review.php's bootstrapValidator (quarter/year/description/
 * recommendation notEmpty) + submitHandler (date required and not in
 * the future, year not in the future, Manual/chapter required, Reviewed
 * By required). Procedure and Section are both explicitly optional in
 * legacy too. There's no Vessel field — legacy's Add form hides it
 * entirely for SHORE records, and every record created here is
 * SHORE-added.
 */
export function buildIspsReviewSchema() {
  return z
    .object({
      manual_chapter_id: optionalId,
      manual_document_id: optionalId,
      manual_section: optionalText,
      review_date: z.string().trim().min(1, "Required field"),
      review_quarter: z.union([z.number(), z.string()]).transform((v) => Number(v)),
      review_year: z.union([z.number(), z.string()]).transform((v) => Number(v)),
      review_description: z.string().trim().min(1, "Required field"),
      review_recommendation: z.string().trim().min(1, "Required field"),
      shore_reviewed_by: z.string().trim().min(1, "Required field"),
      shore_remarks: optionalText,
      present: z.array(presentSchema),
    })
    .superRefine((data, ctx) => {
      if (!data.manual_chapter_id) {
        ctx.addIssue({ code: "custom", path: ["manual_chapter_id"], message: "Please select a Manual." });
      }
      if (data.review_date > today()) {
        ctx.addIssue({ code: "custom", path: ["review_date"], message: "Should not be greater than today." });
      }
      const currentYear = new Date().getFullYear();
      if (data.review_year > currentYear) {
        ctx.addIssue({ code: "custom", path: ["review_year"], message: "Should not be greater than the current year." });
      }
    });
}

export type IspsReviewFormValues = z.infer<ReturnType<typeof buildIspsReviewSchema>>;
