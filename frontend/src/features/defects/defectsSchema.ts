import { z } from "zod";

const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalCode = z.union([z.literal(""), z.string()]).optional();

/**
 * Mirrors DefectRequest (backend), ported from add_defect_list.php's
 * bootstrapValidator + submitHandler: SL No./Date/Compl Code carry the
 * required asterisk in the legacy form. The "required" attribute
 * copy-pasted onto Expected Compl Date/Compl Date is never actually
 * enforced by the submit handler, so both stay optional here too.
 * vessel_id is required only on create (frozen on edit).
 */
export function buildDefectSchema(isCreate: boolean) {
  return z.object({
    vessel_id: isCreate
      ? z.union([z.number(), z.string()]).refine((v) => String(v).length > 0, "Please select a Vessel.")
      : z.union([z.number(), z.string()]).optional(),
    sl_no: z.string().trim().min(1, "Required Field"),
    defect_date: z.string().trim().min(1, "Required Field"),
    description: z.string().trim().min(1, "Required Field"),
    present_status: optionalText,
    priority: optionalCode,
    category: optionalCode,
    raised_by: optionalCode,
    compl_code: z.string().trim().min(1, "Required Field"),
    expected_compl_date: optionalText,
    compl_date: optionalText,
    shore_remarks: optionalText,
  });
}

export type DefectFormValues = z.infer<ReturnType<typeof buildDefectSchema>>;
