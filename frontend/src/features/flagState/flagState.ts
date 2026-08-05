import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface FlagStateReportRow {
  /** A local numeric id normally, but a legacy flagID string when reading from the legacy connection. */
  id: number | string;
  ref_no: string;
  vessel: string;
  added_by: "SHORE" | "VESSEL";
  dateof_inspection: string;
  placeof_inspection: string | null;
  inspector: string | null;
  published: boolean | null;
  is_approved: boolean | null;
  pending_nc_count: number;
  total_nc_count: number;
  can_edit: boolean;
  can_publish: boolean;
  can_approve: boolean;
  can_delete: boolean;
}

export interface FlagStateReportDetail extends FlagStateReportRow {
  vessel_id: number | null;
  flag_cost: string | null;
  shore_remarks: string | null;
  vessel_remarks: string | null;
}

export interface FlagStateReportListResponse {
  columns: DashletColumn[];
  rows: FlagStateReportRow[];
  meta: TableMeta;
}

export interface FlagStateReportOption {
  id: number | string;
  label: string;
}

export interface FlagStateReportOptions {
  vessels: FlagStateReportOption[];
}
