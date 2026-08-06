import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface NonconformityRow {
  /** A local numeric id normally, but a legacy ncID string when reading from the legacy connection. */
  id: number | string;
  ncr_no: string;
  date_of_nc: string;
  added_by: "SHORE" | "VESSEL";
  source_of_nc: string;
  reported_by: string;
  vessel_company: string;
  description: string;
  is_published: boolean | null;
  is_approved: boolean | null;
  status: "CLOSED" | "IN PROGRESS";
  status_color: "green" | "yellow";
  can_edit: boolean;
  can_publish: boolean;
  can_approve: boolean;
  can_reopen: boolean;
}

export interface NonconformityDetail extends NonconformityRow {
  vessel_id: number | null;
  vessel_company_raw: "VESSEL" | "COMPANY";
  company: string | null;
  department_name: string | null;
  reported_by_raw: string | null;
  reporter_name: string | null;
  source_of_nc_raw: string;
  source_of_nc_others: string | null;
  source_of_nc_ref_no: string | null;
  manual_chapter_id: number | null;
  manual_chapter_label: string | null;
  sms_details: string | null;
  root_cause: string | null;
  root_cause_incharge: string | null;
  corrective_action: string | null;
  corrective_action_incharge: string | null;
  corrective_action_date: string | null;
  verification: "COMPLETED" | "FOLLOW-UP" | "ASSISTANCE" | null;
  verification_followup: string | null;
  verification_assistance: string | null;
  verification_dpa: string | null;
  verification_date: string | null;
  close_out_completed: boolean;
  close_out_followup: boolean;
  close_out_followup_nature: string | null;
  close_out_dpa: string | null;
  close_out_date: string | null;
  attach_safety_meeting: boolean;
  attach_record_training: boolean;
  attach_logbook: boolean;
  attach_delivery_note: boolean;
  attach_photo: boolean;
  attach_company_forms: boolean;
  attach_others: boolean;
  attach_others_details: string | null;
}

export interface NonconformityListResponse {
  columns: DashletColumn[];
  rows: NonconformityRow[];
  meta: TableMeta;
}

export interface NonconformityOption {
  id: number | string;
  label: string;
}

export interface NonconformityOptions {
  vessels: NonconformityOption[];
}
