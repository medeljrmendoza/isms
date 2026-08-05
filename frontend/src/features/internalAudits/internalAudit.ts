import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface InternalAuditRow {
  /** A local numeric id normally, but a legacy auditID string when reading from the legacy connection. */
  id: number | string;
  audit_ref: string;
  vessel: string;
  this_date: string;
  placeof_audit: string | null;
  typeof_audit: "ISM" | "ISPS" | "MLC" | "ISM/ISPS/MLC" | null;
  auditor_name: string | null;
  pending_nc_count: number;
  total_nc_count: number;
  can_edit: boolean;
  can_delete: boolean;
}

export interface InternalAuditDetail extends InternalAuditRow {
  vessel_id: number | null;
  department: string | null;
  master_name: string | null;
  chief_engineer: string | null;
  remarks: string | null;
}

export interface InternalAuditListResponse {
  columns: DashletColumn[];
  rows: InternalAuditRow[];
  meta: TableMeta;
}

export interface InternalAuditOption {
  id: number | string;
  label: string;
}

export interface InternalAuditOptions {
  vessels: InternalAuditOption[];
}
