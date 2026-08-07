import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface PscReportRow {
  /** A legacy pscreportid varchar string. */
  id: string;
  ref_no: string;
  vessel: string;
  dateof_inspection: string;
  placeof_inspection: string | null;
  name_psco: string | null;
  mou: string | null;
  pending_nc_count: number;
  total_nc_count: number;
  pending_obs_count: number;
  total_obs_count: number;
  can_edit: boolean;
  can_delete: boolean;
}

export interface PscReportDetail extends PscReportRow {
  vessel_id: string | null;
  mou_id: string | null;
  mou_others: string | null;
  master_name: string | null;
  chief_engineer: string | null;
  is_detained: boolean;
  detained_date: string | null;
  detained_time: string | null;
  is_released: boolean;
  released_date: string | null;
  released_time: string | null;
  closing_date: string | null;
  remarks: string | null;
}

export interface PscReportListResponse {
  columns: DashletColumn[];
  rows: PscReportRow[];
  meta: TableMeta;
}

export interface PscReportOption {
  id: string;
  label: string;
}

export interface PscReportOptions {
  vessels: PscReportOption[];
  mou_authorities: PscReportOption[];
}
