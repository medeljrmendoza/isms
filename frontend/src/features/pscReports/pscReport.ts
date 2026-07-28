import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface PscReportRow {
  id: number;
  ref_no: string;
  vessel: string;
  dateof_inspection: string;
  mou: string | null;
  pending_nc_count: number;
  total_nc_count: number;
  can_edit: boolean;
  can_delete: boolean;
  can_reopen: boolean;
}

export interface PscReportDetail extends PscReportRow {
  vessel_id: number | null;
  placeof_inspection: string | null;
  mou_id: number | null;
  mou_others: string | null;
  name_psco: string | null;
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
  id: number;
  label: string;
}

export interface PscReportOptions {
  vessels: PscReportOption[];
  mou_authorities: PscReportOption[];
}
