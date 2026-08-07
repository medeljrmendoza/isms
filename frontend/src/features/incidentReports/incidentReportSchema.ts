import { z } from "zod";

const today = () => new Date().toISOString().slice(0, 10);
const optionalText = z.string().trim().optional().or(z.literal(""));
const optionalId = z
  .union([z.string(), z.null()])
  .optional()
  .transform((v) => (v === null || v === undefined || v === "" ? null : v));

const rootCauseRowSchema = z.object({
  root_cause_id: optionalId,
  root_cause_other: optionalText,
  investigation: z.string().trim().min(1, "Required field"),
  analysis: z.string().trim().min(1, "Required field"),
  corrective_actions: z.string().trim().min(1, "Required field"),
});

const personRowSchema = z.object({
  person_name: z.string().trim().min(1, "Required field"),
  position: z.string().trim().min(1, "Required field"),
});

/**
 * Mirrors IncidentReportRequest (backend) and, before that,
 * admin/incident/add_incident_report_v.php's submitHandler. `vessel_id`
 * is only required on create — vessel attribution is frozen after that.
 * Lookup IDs (nature_of_incident_id, incident_location_id, etc.) are
 * legacy varchar IDs, not local numeric FKs.
 */
export function buildIncidentReportSchema(isCreate: boolean) {
  return z
    .object({
      vessel_id: optionalId,
      voyage_no: optionalText,
      dateof_report: z.string().trim().min(1, "Required field"),
      report_no: optionalText,
      master_name: optionalText,
      chief_engineer_name: optionalText,
      person_reporting: optionalText,
      nature_type: z.enum(["accident", "hazardous_occurrence"], { message: "Required field" }),
      statementof_work: z.string().trim().min(1, "Required field"),

      nature_of_incident_id: optionalId,
      accident_collision: optionalText,
      others: optionalText,
      bac: z.enum(["NO", "YES"]).nullable().optional(),
      vdr: z.enum(["NO", "YES"]).nullable().optional(),
      dateof_event: optionalText,
      timeof_event: optionalText,
      zone: optionalText,
      country: optionalText,
      speed: optionalText,
      course: optionalText,
      draft_forward: optionalText,
      draft_alt: optionalText,
      wind_direction: optionalText,
      direction_sea: optionalText,
      direction_swell: optionalText,
      geographical_location: optionalText,
      port_departure: optionalText,
      date_departure: optionalText,
      port_which_bound: optionalText,
      type_cargo: optionalText,
      cargo_quantity: optionalText,
      special_requirement: optionalText,
      atmospheric_clear: z.boolean().optional(),
      atmospheric_partly_cloudy: z.boolean().optional(),
      atmospheric_overcast: z.boolean().optional(),
      atmospheric_fog: z.boolean().optional(),
      atmospheric_rain: z.boolean().optional(),
      atmospheric_snow: z.boolean().optional(),
      atmospheric_other: z.boolean().optional(),
      atmospheric_other_name: optionalText,
      distance1: z.boolean().optional(),
      distance2: z.boolean().optional(),
      distance3: z.boolean().optional(),
      sea1: z.boolean().optional(),
      sea2: z.boolean().optional(),
      sea3: z.boolean().optional(),
      crew_onboard: z.number().nullable().optional(),
      other_onboard: z.number().nullable().optional(),
      total_onboard: z.number().nullable().optional(),
      crew_dead: z.number().nullable().optional(),
      other_dead: z.number().nullable().optional(),
      total_dead: z.number().nullable().optional(),
      crew_missing: z.number().nullable().optional(),
      other_missing: z.number().nullable().optional(),
      total_missing: z.number().nullable().optional(),
      crew_injured: z.number().nullable().optional(),
      other_injured: z.number().nullable().optional(),
      total_injured: z.number().nullable().optional(),
      fs_ro: z.enum(["NO", "YES"]).nullable().optional(),

      hazardous_occurrence_type: z.enum(["unsafe_act", "unsafe_condition", "near_miss"]).nullable().optional(),
      incident_location_id: optionalId,
      location_other: optionalText,
      ship_position: optionalText,
      incident_operation_id: optionalId,
      ship_operation_other: optionalText,
      hazardous_occurrence_ppe_used: z.enum(["NO", "YES", "NA"]).nullable().optional(),
      hazardous_occurrence_ppe_used_comment: optionalText,
      hazardous_occurrence_severity: z.enum(["HIGH", "MEDIUM", "LOW"]).nullable().optional(),
      hazardous_occurrence_severity_comment: optionalText,
      hazardous_occurrence_likelihood: z.enum(["HIGH", "MEDIUM", "LOW"]).nullable().optional(),
      hazardous_occurrence_likelihood_comment: optionalText,
      subject_investigation: z.enum(["NO", "YES"]).nullable().optional(),
      evidence_safety_meeting: z.boolean().optional(),
      evidence_certificate: z.boolean().optional(),
      evidence_logbook: z.boolean().optional(),
      evidence_delivery: z.boolean().optional(),
      evidence_photo: z.boolean().optional(),
      evidence_company: z.boolean().optional(),
      evidence_others: z.boolean().optional(),
      evidence_others_name: optionalText,
      causal_factor: optionalText,
      intermediate_cause: optionalText,
      shore_root_cause_summary: optionalText,

      severity_itp: z.enum(["FATALITY", "FAC", "LWC", "MTC", "PPD", "PTD", "RWC"]).nullable().optional(),
      comment_itp: optionalText,
      location_of_injury_id: optionalId,
      type_of_injury_id: optionalId,
      other_typeof_injury: optionalText,
      other_affected_area: optionalText,
      severity_itv: z.enum(["low", "medium", "high"]).nullable().optional(),
      comment_itv: optionalText,

      root_causes: z.array(rootCauseRowSchema).optional().default([]),
      persons: z.array(personRowSchema).optional().default([]),

      signed_by: z.string().trim().min(1, "Required field"),
      date_signed: z.string().trim().min(1, "Required field"),
      vessel_remarks: optionalText,
      date_received: z.string().trim().min(1, "Required field"),
      reviewed_by: optionalText,
      investigator: optionalText,
      dpa: z.string().trim().min(1, "Required field"),
      closing_date: optionalText,
    })
    .superRefine((data, ctx) => {
      const todayStr = today();
      const isAccident = data.nature_type === "accident";
      const isHazardous = data.nature_type === "hazardous_occurrence";

      if (isCreate && !data.vessel_id) {
        ctx.addIssue({ code: "custom", path: ["vessel_id"], message: "Please select a vessel." });
      }
      if (data.dateof_report > todayStr) {
        ctx.addIssue({ code: "custom", path: ["dateof_report"], message: "Should not be greater than today." });
      }

      if (isAccident) {
        if (!data.nature_of_incident_id) {
          ctx.addIssue({ code: "custom", path: ["nature_of_incident_id"], message: "Please select the nature of incident." });
        }
        if (!data.dateof_event) {
          ctx.addIssue({ code: "custom", path: ["dateof_event"], message: "Please input Date of Event." });
        } else if (data.dateof_event > todayStr) {
          ctx.addIssue({ code: "custom", path: ["dateof_event"], message: "Should not be greater than today." });
        }
        if (data.date_departure && data.date_departure > todayStr) {
          ctx.addIssue({ code: "custom", path: ["date_departure"], message: "Should not be greater than today." });
        }
        if (data.atmospheric_other && !data.atmospheric_other_name) {
          ctx.addIssue({ code: "custom", path: ["atmospheric_other_name"], message: "Please specify the other condition." });
        }
      }

      if (isHazardous) {
        if (!data.hazardous_occurrence_type) {
          ctx.addIssue({ code: "custom", path: ["hazardous_occurrence_type"], message: "Please select the type." });
        }
        if (!data.hazardous_occurrence_severity) {
          ctx.addIssue({ code: "custom", path: ["hazardous_occurrence_severity"], message: "Please select the severity." });
        }
        if (!data.hazardous_occurrence_likelihood) {
          ctx.addIssue({ code: "custom", path: ["hazardous_occurrence_likelihood"], message: "Please select the likelihood." });
        }
        if (!data.incident_location_id) {
          ctx.addIssue({ code: "custom", path: ["incident_location_id"], message: "Please select the location." });
        }
        if (!data.incident_operation_id) {
          ctx.addIssue({ code: "custom", path: ["incident_operation_id"], message: "Please select the ship's operation." });
        }
        if (data.evidence_others && !data.evidence_others_name) {
          ctx.addIssue({ code: "custom", path: ["evidence_others_name"], message: "Please specify the other evidence." });
        }
      }

      if (data.severity_itp) {
        if (!data.type_of_injury_id) {
          ctx.addIssue({ code: "custom", path: ["type_of_injury_id"], message: "Please select the type of injury." });
        }
        if (!data.location_of_injury_id) {
          ctx.addIssue({ code: "custom", path: ["location_of_injury_id"], message: "Please select the affected area." });
        }
      }

      if (data.date_signed > todayStr) {
        ctx.addIssue({ code: "custom", path: ["date_signed"], message: "Should not be greater than today." });
      }
      if (data.date_received > todayStr) {
        ctx.addIssue({ code: "custom", path: ["date_received"], message: "Should not be greater than today." });
      }
      if (data.closing_date && data.closing_date > todayStr) {
        ctx.addIssue({ code: "custom", path: ["closing_date"], message: "Should not be greater than today." });
      }
    });
}

export type IncidentReportFormValues = z.infer<ReturnType<typeof buildIncidentReportSchema>>;
