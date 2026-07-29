import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);
const optionalText = z.string().trim().optional().or(z.literal(""));

const crewSchema = z.object({
  crew_name: z.string().trim().min(1, "Required"),
});

/**
 * Mirrors DrillReportRequest (backend) and, before that,
 * admin/drillreports/add_drill_report.php's bootstrapValidator (master/
 * time/position notEmpty) + submitHandler (date and report date required
 * and not in the future, at least one crew member with a non-empty
 * name). This is edit-only — there's no vessel/drill/date-origin field
 * here at all, since legacy never lets shore set them; this admin can
 * only annotate a report the vessel already submitted.
 */
export const drillReportSchema = z
  .object({
    master_name: z.string().trim().min(1, "Required field"),
    drill_date: z.string().trim().min(1, "Required field"),
    drill_time_from: z.string().trim().min(1, "Required field"),
    drill_position: z.string().trim().min(1, "Required field"),
    drill_details: optionalText,
    drill_deficiencies: optionalText,
    drill_corrective_action: optionalText,
    report_date: z.string().trim().min(1, "Required field"),
    vessel_remarks: optionalText,
    receipt_date: optionalText,
    shore_remarks: optionalText,
    crew: z.array(crewSchema).min(1, "Please add at least one crew member."),
  })
  .superRefine((data, ctx) => {
    if (data.drill_date > today()) {
      ctx.addIssue({ code: "custom", path: ["drill_date"], message: "Should not be greater than today." });
    }
    if (data.report_date > today()) {
      ctx.addIssue({ code: "custom", path: ["report_date"], message: "Should not be greater than today." });
    }
  });

export type DrillReportFormValues = z.infer<typeof drillReportSchema>;
