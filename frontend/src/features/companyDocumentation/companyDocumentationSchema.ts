import { z } from "zod";

const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalId = z
  .union([z.number(), z.string()])
  .nullable()
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v)));

/**
 * Mirrors CompanyDocumentationRecordRequest (backend) and, before that,
 * add_company_documentation_v.php's bootstrapValidator (only Date Issued
 * is `notEmpty`) + submitHandler's create-only Document check. Page No.'s
 * PF-linked validation is dropped along with the field itself.
 */
export function buildCompanyDocumentationSchema(isCreate: boolean) {
  return z
    .object({
      company_document_id: optionalId,
      doc_number: optionalText,
      issuing_body: optionalText,
      date_issued: z.string().trim().min(1, "Required field"),
      date_expired: optionalText,
      date_range_from: optionalText,
      date_range_to: optionalText,
      is_printer_friendly: z.boolean().optional(),
      remarks: optionalText,
    })
    .superRefine((data, ctx) => {
      if (isCreate && !data.company_document_id) {
        ctx.addIssue({ code: "custom", path: ["company_document_id"], message: "Please select a document." });
      }
      if (data.date_range_from && data.date_range_to && data.date_range_to < data.date_range_from) {
        ctx.addIssue({ code: "custom", path: ["date_range_to"], message: "Must not be before Date Range From." });
      }
    });
}

export type CompanyDocumentationFormValues = z.infer<ReturnType<typeof buildCompanyDocumentationSchema>>;
