import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface IncidentReportRow {
  /** A local numeric id normally, but a legacy incidentid string when reading from the legacy connection. */
  id: number | string;
  vessel: string;
  dateof_report: string;
  report_no: string | null;
  nature: "ACCIDENT" | "HAZARDOUS OCCURRENCE";
  type: string;
  added_by: "SHORE" | "VESSEL";
  published: boolean | null;
  is_approved: boolean;
  status: "CLOSED" | "IN PROGRESS";
  status_color: "green" | "yellow";
  can_edit: boolean;
  can_publish: boolean;
  can_approve: boolean;
  can_reopen: boolean;
  can_delete: boolean;
}

export interface IncidentRootCauseRow {
  id?: number;
  root_cause_id: number | null;
  root_cause_category_label?: string | null;
  root_cause_label?: string | null;
  root_cause_other: string | null;
  investigation: string | null;
  analysis: string | null;
  corrective_actions: string | null;
}

export interface IncidentPersonRow {
  id?: number;
  person_name: string;
  position: string | null;
}

export interface IncidentReportDetail extends IncidentReportRow {
  vessel_id: number | null;
  voyage_no: string | null;
  master_name: string | null;
  chief_engineer_name: string | null;
  person_reporting: string | null;
  nature_type: "accident" | "hazardous_occurrence";
  nature_of_incident_id: number | null;
  nature_of_incident_label: string | null;
  hazardous_occurrence_type: "unsafe_act" | "unsafe_condition" | "near_miss" | null;
  others: string | null;
  accident_collision: string | null;
  statementof_work: string | null;
  bac: "NO" | "YES" | null;
  vdr: "NO" | "YES" | null;
  dateof_event: string | null;
  timeof_event: string | null;
  zone: string | null;
  country: string | null;
  speed: string | null;
  course: string | null;
  draft_forward: string | null;
  draft_alt: string | null;
  wind_direction: string | null;
  direction_sea: string | null;
  direction_swell: string | null;
  geographical_location: string | null;
  port_departure: string | null;
  date_departure: string | null;
  port_which_bound: string | null;
  type_cargo: string | null;
  cargo_quantity: string | null;
  special_requirement: string | null;
  atmospheric_clear: boolean;
  atmospheric_partly_cloudy: boolean;
  atmospheric_overcast: boolean;
  atmospheric_fog: boolean;
  atmospheric_rain: boolean;
  atmospheric_snow: boolean;
  atmospheric_other: boolean;
  atmospheric_other_name: string | null;
  distance1: boolean;
  distance2: boolean;
  distance3: boolean;
  sea1: boolean;
  sea2: boolean;
  sea3: boolean;
  crew_onboard: number | null;
  other_onboard: number | null;
  total_onboard: number | null;
  crew_dead: number | null;
  other_dead: number | null;
  total_dead: number | null;
  crew_missing: number | null;
  other_missing: number | null;
  total_missing: number | null;
  crew_injured: number | null;
  other_injured: number | null;
  total_injured: number | null;
  fs_ro: "NO" | "YES" | null;
  incident_location_id: number | null;
  incident_location_label: string | null;
  location_other: string | null;
  ship_position: string | null;
  incident_operation_id: number | null;
  incident_operation_label: string | null;
  ship_operation_other: string | null;
  hazardous_occurrence_ppe_used: "NO" | "YES" | "NA" | null;
  hazardous_occurrence_ppe_used_comment: string | null;
  hazardous_occurrence_severity: "HIGH" | "MEDIUM" | "LOW" | null;
  hazardous_occurrence_severity_comment: string | null;
  hazardous_occurrence_likelihood: "HIGH" | "MEDIUM" | "LOW" | null;
  hazardous_occurrence_likelihood_comment: string | null;
  subject_investigation: "NO" | "YES" | null;
  evidence_safety_meeting: boolean;
  evidence_certificate: boolean;
  evidence_logbook: boolean;
  evidence_delivery: boolean;
  evidence_photo: boolean;
  evidence_company: boolean;
  evidence_others: boolean;
  evidence_others_name: string | null;
  causal_factor: string | null;
  intermediate_cause: string | null;
  shore_root_cause_summary: string | null;
  severity_itp: "FATALITY" | "FAC" | "LWC" | "MTC" | "PPD" | "PTD" | "RWC" | null;
  comment_itp: string | null;
  location_of_injury_id: number | null;
  location_of_injury_label: string | null;
  type_of_injury_id: number | null;
  type_of_injury_label: string | null;
  other_typeof_injury: string | null;
  other_affected_area: string | null;
  severity_itv: "low" | "medium" | "high" | null;
  comment_itv: string | null;
  signed_by: string | null;
  date_signed: string | null;
  vessel_remarks: string | null;
  date_received: string | null;
  reviewed_by: string | null;
  investigator: string | null;
  dpa: string | null;
  closing_date: string | null;
  root_causes: IncidentRootCauseRow[];
  persons: IncidentPersonRow[];
}

export interface IncidentReportListResponse {
  columns: DashletColumn[];
  rows: IncidentReportRow[];
  meta: TableMeta;
}

export interface IncidentReportOption {
  id: number | string;
  label: string;
}

export interface RootCauseCategoryOption {
  id: number;
  label: string;
  root_causes: IncidentReportOption[];
}

export interface IncidentReportOptions {
  vessels: IncidentReportOption[];
  years: string[];
  nature_of_incidents: IncidentReportOption[];
  incident_locations: IncidentReportOption[];
  incident_operations: IncidentReportOption[];
  types_of_injury: IncidentReportOption[];
  locations_of_injury: IncidentReportOption[];
  root_cause_categories: RootCauseCategoryOption[];
}
