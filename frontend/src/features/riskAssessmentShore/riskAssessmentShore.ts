export type RiskAssessmentShoreReportType = "SHORE" | "VESSEL";

export interface RiskAssessmentShoreRow {
  /** A local numeric id normally, but a legacy riskID string when reading from the legacy connection. */
  id: number | string;
  report_no: string;
  report_type: RiskAssessmentShoreReportType;
  vessel: string;
  risk_date: string | null;
  port: string | null;
  category: string;
  task: string;
  approval_from_shore: boolean;
  shore_is_approved: boolean;
  approval_from_marine: boolean;
  marine_is_approved: boolean;
  date_closed: string | null;
  hazard_count: number;
  can_edit: boolean;
  can_delete: boolean;
  can_reopen: boolean;
}

export interface RiskAssessmentShoreHazard {
  id: number;
  arrangement: number;
  unwanted_consequences: string | null;
  underlying_causes: string | null;
  severity: number | null;
  likelihood: number | null;
  risk: string | null;
  existing_control: string | null;
  additional_control: string | null;
  re_severity: number | null;
  re_likelihood: number | null;
  re_risk: string | null;
}

export interface RiskAssessmentShorePerson {
  id: number;
  arrangement: number;
  person_details: string;
}

export interface RiskAssessmentShoreDetail extends RiskAssessmentShoreRow {
  vessel_id: number | null;
  risk_schedule: string | null;
  department: string | null;
  activity: string | null;
  risk_category_shore_id: number | null;
  other_category_name: string | null;
  risk_operation_shore_id: number | null;
  other_operation_name: string | null;
  overall_risk: string | null;
  remarks: string | null;
  date_approved: string | null;
  shore_remarks: string | null;
  marine_date_approved: string | null;
  marine_remarks: string | null;
  hazards: RiskAssessmentShoreHazard[];
  people: RiskAssessmentShorePerson[];
}

export interface RiskAssessmentShoreOption {
  id: number | string;
  label: string;
}

export interface RiskAssessmentShoreOptions {
  vessels: RiskAssessmentShoreOption[];
  years: number[];
}
