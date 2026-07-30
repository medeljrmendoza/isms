import { z } from "zod";

const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalId = z
  .union([z.number(), z.string()])
  .nullable()
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v)));

/**
 * Mirrors VesselDocumentRecordRequest (backend) and, before that,
 * add_vessel_documentation_v.php's bootstrapValidator (only Date Issued
 * is `notEmpty`) + submitHandler's create-only Vessel/Document checks.
 * Attachment upload is excluded — no S3 infra in this migration.
 */
export function buildVesselDocumentationSchema(isCreate: boolean) {
  return z
    .object({
      vessel_id: optionalId,
      vessel_document_id: optionalId,
      doc_number: optionalText,
      issuing_body: optionalText,
      date_issued: z.string().trim().min(1, "Required field"),
      date_expired: optionalText,
      date_range_from: optionalText,
      date_range_to: optionalText,
      is_printer_friendly: z.boolean().optional(),
      shore_remarks: optionalText,
      vessel_remarks: optionalText,
    })
    .superRefine((data, ctx) => {
      if (isCreate && !data.vessel_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select a vessel." });
      }
      if (isCreate && !data.vessel_document_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_document_id"], message: "Please select a document." });
      }
      if (data.date_range_from && data.date_range_to && data.date_range_to < data.date_range_from) {
        ctx.addIssue({ code: "custom", path: ["date_range_to"], message: "Must not be before Date Range From." });
      }
    });
}

export type VesselDocumentationFormValues = z.infer<ReturnType<typeof buildVesselDocumentationSchema>>;
