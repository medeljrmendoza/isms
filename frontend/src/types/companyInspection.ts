import type { DashletColumn, TableMeta } from "./dashboard";

export interface CompanyInspectionRow {
  id: number;
  audit_ref: string;
  /** Already resolved to the vessel's display name or the company name. */
  vessel_company: string;
  this_date: string;
  placeof_audit: string | null;
  audit_type: string | null;
  audit_kind: string | null;
  pending_nc_count: number;
  total_nc_count: number;
  can_edit: boolean;
  can_delete: boolean;
}

export interface CompanyInspectionDetail extends CompanyInspectionRow {
  vessel_company_raw: "VESSEL" | "COMPANY";
  vessel_id: number | null;
  company: string | null;
  department: string | null;
  audit_type_id: number | null;
  audit_kind_id: number | null;
  inspector_name: string | null;
  master_name: string | null;
  chief_engineer: string | null;
  remarks: string | null;
}

export interface CompanyInspectionListResponse {
  columns: DashletColumn[];
  rows: CompanyInspectionRow[];
  meta: TableMeta;
}

export interface CompanyInspectionOption {
  id: number;
  label: string;
}

export interface CompanyInspectionOptions {
  vessels: CompanyInspectionOption[];
  audit_types: CompanyInspectionOption[];
  audit_kinds: CompanyInspectionOption[];
}
