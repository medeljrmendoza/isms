import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);
const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalId = z
  .union([z.number(), z.string()])
  .nullable()
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v)));

/**
 * Mirrors PscReportRequest (backend) and, before that,
 * admin/psc/add_psc_report.php's bootstrapValidator + submitHandler.
 * `vessel_id` is only required on create — vessel attribution is frozen
 * after that, same as Nonconformities/Incident Report.
 */
export function buildPscReportSchema(isCreate: boolean) {
  return z
    .object({
      ref_no: z.string().trim().min(1, "Required field"),
      vessel_id: optionalId,
      dateof_inspection: z.string().trim().min(1, "Required field"),
      placeof_inspection: z.string().trim().min(1, "Required field"),
      mou_id: optionalId,
      mou_others: optionalText,
      name_psco: optionalText,
      master_name: optionalText,
      chief_engineer: optionalText,
      is_detained: z.boolean(),
      detained_date: optionalText,
      detained_time: optionalText,
      is_released: z.boolean(),
      released_date: optionalText,
      released_time: optionalText,
      closing_date: optionalText,
      remarks: optionalText,
    })
    .superRefine((data, ctx) => {
      const todayStr = today();

      if (isCreate && !data.vessel_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select a vessel." });
      }
      if (data.dateof_inspection > todayStr) {
        ctx.addIssue({ code: "custom", path: ["dateof_inspection"], message: "Should not be greater than today." });
      }

      if (data.is_detained) {
        if (!data.detained_date) {
          ctx.addIssue({ code: "custom", path: ["detained_date"], message: "Please input Date Detained." });
        } else if (data.detained_date > todayStr) {
          ctx.addIssue({ code: "custom", path: ["detained_date"], message: "Should not be greater than today." });
        }
        if (!data.detained_time) {
          ctx.addIssue({ code: "custom", path: ["detained_time"], message: "Please input Time Detained." });
        }

        if (data.is_released) {
          if (!data.released_date) {
            ctx.addIssue({ code: "custom", path: ["released_date"], message: "Please input Date Released." });
          } else if (data.released_date > todayStr) {
            ctx.addIssue({ code: "custom", path: ["released_date"], message: "Should not be greater than today." });
          }
          if (!data.released_time) {
            ctx.addIssue({ code: "custom", path: ["released_time"], message: "Please input Time Released." });
          }
        }
      }

      if (data.closing_date && data.closing_date > todayStr) {
        ctx.addIssue({ code: "custom", path: ["closing_date"], message: "Should not be greater than today." });
      }
    });
}

export type PscReportFormValues = z.infer<ReturnType<typeof buildPscReportSchema>>;
