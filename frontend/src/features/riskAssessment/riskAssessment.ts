export interface RiskAssessmentRow {
  id: number;
  report_no: string;
  vessel: string;
  risk_date: string | null;
  port: string | null;
  category: string;
  task: string;
  approval_from_shore: boolean;
  shore_is_approved: boolean;
  approval_from_marine: boolean;
  marine_is_approved: boolean;
  hazard_count: number;
  can_edit: boolean;
}

export interface RiskAssessmentHazard {
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

export interface RiskAssessmentPerson {
  id: number;
  arrangement: number;
  person_details: string;
}

export interface RiskAssessmentDetail extends RiskAssessmentRow {
  vessel_id: number;
  risk_schedule: string | null;
  department: string | null;
  activity: string | null;
  risk_category_id: number | null;
  other_category_name: string | null;
  risk_operation_id: number | null;
  other_operation_name: string | null;
  overall_risk: string | null;
  master: string | null;
  ce_co: string | null;
  vessel_remarks: string | null;
  date_approved: string | null;
  shore_remarks: string | null;
  marine_date_approved: string | null;
  marine_remarks: string | null;
  date_closed: string | null;
  hazards: RiskAssessmentHazard[];
  people: RiskAssessmentPerson[];
}

export interface RiskAssessmentOption {
  id: number;
  label: string;
}

export interface RiskAssessmentOptions {
  vessels: RiskAssessmentOption[];
  years: number[];
}

export interface RiskAssessmentApprovalPayload {
  approved: boolean;
  date_approved?: string | null;
  remarks?: string | null;
}
