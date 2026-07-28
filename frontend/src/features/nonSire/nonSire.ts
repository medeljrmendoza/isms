import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface NonSireReportRow {
  id: number;
  vessel: string;
  added_by: "SHORE" | "VESSEL";
  dateof_inspection: string;
  placeof_inspection: string | null;
  company_name: string | null;
  inspector_name: string | null;
  inspection_type: string | null;
  pass_fail: "PASS" | "FAIL" | "N/A" | null;
  published: boolean | null;
  is_approved: boolean | null;
  can_edit: boolean;
  can_publish: boolean;
  can_approve: boolean;
  can_delete: boolean;
}

export interface NonSireReportDetail extends NonSireReportRow {
  vessel_id: number | null;
  sire_cost: string | null;
  shore_remarks: string | null;
  vessel_remarks: string | null;
}

export interface NonSireReportListResponse {
  columns: DashletColumn[];
  rows: NonSireReportRow[];
  meta: TableMeta;
}

export interface NonSireReportOption {
  id: number;
  label: string;
}

export interface NonSireReportOptions {
  vessels: NonSireReportOption[];
}
