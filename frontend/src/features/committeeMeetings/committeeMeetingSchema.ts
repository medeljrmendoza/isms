import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);
const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalId = z
  .union([z.number(), z.string()])
  .nullable()
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : Number(v)));

const meetingTypeSchema = z.object({
  committee_meeting_type_id: z.number(),
  name: z.string(),
  type_other: optionalText,
});

const personSchema = z.object({
  name: z.string().trim().min(1, "Required"),
});

const topicSchema = z.object({
  topic_name: z.string().trim().min(1, "Required"),
  meeting_details: optionalText,
  meeting_comments: optionalText,
});

/**
 * Mirrors CommitteeMeetingRequest (backend) and, before that,
 * admin/commiteemeeting/add_committee_meeting.php's bootstrapValidator
 * (position/time/chairman/incharge notEmpty) + submitHandler (shore/
 * vessel radio required, vessel required when it's "VESSEL", date
 * required and not in the future, at least one meeting type, "Others"
 * requires its free-text detail). shore_vessel_meeting/vessel_id only
 * matter on create — both freeze after that, same as vessel elsewhere.
 * vessel_remarks is excluded — legacy renders it read-only in this
 * admin form.
 */
export function buildCommitteeMeetingSchema(isCreate: boolean) {
  return z
    .object({
      shore_vessel_meeting: z.enum(["SHORE", "VESSEL"]),
      vessel_id: optionalId,
      meeting_date: z.string().trim().min(1, "Required field"),
      meeting_position: z.string().trim().min(1, "Required field"),
      meeting_time: z.string().trim().min(1, "Required field"),
      chairman: z.string().trim().min(1, "Required field"),
      incharge: z.string().trim().min(1, "Required field"),
      shore_remarks: optionalText,
      meeting_types: z.array(meetingTypeSchema).min(1, "Select at least one meeting type."),
      attendees: z.array(personSchema),
      members: z.array(personSchema),
      topics: z.array(topicSchema),
    })
    .superRefine((data, ctx) => {
      if (isCreate && data.shore_vessel_meeting === "VESSEL" && !data.vessel_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select a vessel." });
      }
      if (data.meeting_date > today()) {
        ctx.addIssue({ code: "custom", path: ["meeting_date"], message: "Should not be greater than today." });
      }
      data.meeting_types.forEach((type, index) => {
        if (type.name === "OTHERS" && !type.type_other) {
          ctx.addIssue({ code: "custom", path: ["meeting_types", index, "type_other"], message: "Please specify the other meeting type." });
        }
      });
    });
}

export type CommitteeMeetingFormValues = z.infer<ReturnType<typeof buildCommitteeMeetingSchema>>;
