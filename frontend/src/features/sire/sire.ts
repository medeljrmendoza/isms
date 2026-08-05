import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface SireReportRow {
  /** A local numeric id normally, but a legacy sireID string when reading from the legacy connection. */
  id: number | string;
  vessel: string;
  added_by: "SHORE" | "VESSEL";
  dateof_inspection: string;
  placeof_inspection: string | null;
  company_name: string | null;
  inspector_name: string | null;
  pass_fail: "PASS" | "FAIL" | "N/A" | null;
  published: boolean | null;
  is_approved: boolean | null;
  can_edit: boolean;
  can_publish: boolean;
  can_approve: boolean;
  can_delete: boolean;
}

export interface SireReportDetail extends SireReportRow {
  vessel_id: number | null;
  sire_cost: string | null;
  shore_remarks: string | null;
  vessel_remarks: string | null;
}

export interface SireReportListResponse {
  columns: DashletColumn[];
  rows: SireReportRow[];
  meta: TableMeta;
}

export interface SireReportOption {
  id: number | string;
  label: string;
}

export interface SireReportOptions {
  vessels: SireReportOption[];
}
