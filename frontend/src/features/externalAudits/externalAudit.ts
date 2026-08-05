import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface ExternalAuditRow {
  /** A local numeric id normally, but a legacy externalID string when reading from the legacy connection. */
  id: number | string;
  ref_no: string;
  vessel: string;
  added_by: "SHORE" | "VESSEL";
  dateof_audit: string;
  portof_audit: string | null;
  typeof_audit: "ISM" | "ISPS" | "MLC" | "ISM/ISPS/MLC" | null;
  published: boolean | null;
  is_approved: boolean;
  pending_nc_count: number;
  total_nc_count: number;
  can_edit: boolean;
  can_publish: boolean;
  can_approve: boolean;
  can_delete: boolean;
}

export interface ExternalAuditDetail extends ExternalAuditRow {
  vessel_id: number | null;
  department: string | null;
  master_name: string | null;
  chief_engineer: string | null;
  auditor_name: string | null;
  shore_remarks: string | null;
  vessel_remarks: string | null;
}

export interface ExternalAuditListResponse {
  columns: DashletColumn[];
  rows: ExternalAuditRow[];
  meta: TableMeta;
}

export interface ExternalAuditOption {
  id: number | string;
  label: string;
}

export interface ExternalAuditOptions {
  vessels: ExternalAuditOption[];
}
