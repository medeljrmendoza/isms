import { z } from "zod";

const optionalText = z.string().trim().optional().or(z.literal(""));

const inventoryLineSchema = z.object({
  pms_part_id: z.number(),
  part_name: z.string().optional(),
  equipment_name: z.string().optional(),
  new_qty: z.union([z.number(), z.string()]).transform((v) => (v === "" ? 0 : Number(v))),
  reconditioned_qty: z.union([z.number(), z.string()]).transform((v) => (v === "" ? 0 : Number(v))),
});

/** Mirrors PmsAdhocRequest (backend), ported from add_pms_work_plan_v.php's bootstrapValidator + submitHandler. */
export function buildPmsWorkPlanSchema(isCreate: boolean) {
  return z
    .object({
      vessel_id: isCreate
        ? z.union([z.number(), z.string()]).transform((v) => Number(v)).refine((v) => v > 0, "Please select a Vessel.")
        : z.union([z.number(), z.string()]).optional(),
      type: z.enum(["EQUIPMENT", "LOCATION"]),
      pms_department_id: z.union([z.number(), z.string()]).nullable().optional(),
      pms_equipment_id: z.union([z.number(), z.string()]).nullable().optional(),
      pms_part_id: z.union([z.number(), z.string()]).nullable().optional(),
      location: optionalText,
      sub_location: optionalText,
      activity_name: z.string().trim().min(1, "Required Field"),
      pms_job_class_id: z.union([z.number(), z.string()]).nullable().optional(),
      pms_job_type_id: z.union([z.number(), z.string()]).nullable().optional(),
      incharge: z.string().trim().min(1, "Required Field"),
      assignee: optionalText,
      work_procedure: optionalText,
      date_of_activity: z.string().trim().min(1, "Required Field"),
      description: z.string().trim().min(1, "Required Field"),
      remarks: optionalText,
      inventory: z.array(inventoryLineSchema),
    })
    .superRefine((data, ctx) => {
      if (data.type === "EQUIPMENT" && !data.pms_equipment_id) {
        ctx.addIssue({ code: "custom", path: ["pms_equipment_id"], message: "Please select a Component." });
      }
      if (data.type === "LOCATION") {
        if (!data.pms_department_id) {
          ctx.addIssue({ code: "custom", path: ["pms_department_id"], message: "Please select a Department." });
        }
        if (!data.location) {
          ctx.addIssue({ code: "custom", path: ["location"], message: "Required Field" });
        }
      }
      const today = new Date().toISOString().slice(0, 10);
      if (data.date_of_activity > today) {
        ctx.addIssue({ code: "custom", path: ["date_of_activity"], message: "Should not be greater than today." });
      }
      data.inventory.forEach((line, index) => {
        if (line.new_qty < 0) {
          ctx.addIssue({ code: "custom", path: ["inventory", index, "new_qty"], message: "QTY less than 0 is not acceptable." });
        }
        if (line.reconditioned_qty < 0) {
          ctx.addIssue({ code: "custom", path: ["inventory", index, "reconditioned_qty"], message: "QTY less than 0 is not acceptable." });
        }
      });
    });
}

export type PmsWorkPlanFormValues = z.infer<ReturnType<typeof buildPmsWorkPlanSchema>>;
