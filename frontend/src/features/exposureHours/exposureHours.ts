import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface ExposureHoursSummaryRow {
  /** A local numeric vessel id normally, but a legacy vesID string when reading from the legacy connection. */
  vessel_id: number | string;
  vessel: string;
  no_of_fat: number;
  no_of_ptd: number;
  no_of_ppd: number;
  no_of_lwc: number;
  no_of_rwc: number;
  no_of_mtc: number;
  total_hours: number;
  lti: number;
  trc: number;
  ltif: number;
  trcf: number;
}

export interface ExposureHoursSummaryTotals {
  fat: number;
  ptd: number;
  ppd: number;
  lwc: number;
  rwc: number;
  mtc: number;
  total_hours: number;
  lti: number;
  trc: number;
  ltif: number;
  trcf: number;
}

export interface ExposureHoursSummaryResponse {
  rows: ExposureHoursSummaryRow[];
  totals: ExposureHoursSummaryTotals;
}

export interface ExposureHoursRecordRow {
  /** A local numeric id normally, but a legacy ehRecordID string when reading from the legacy connection. */
  id: number | string;
  added_by: "SHORE" | "VESSEL";
  date_from: string;
  date_to: string;
  no_of_crew: number;
  no_of_fat: number;
  no_of_ptd: number;
  no_of_ppd: number;
  no_of_lwc: number;
  no_of_rwc: number;
  no_of_mtc: number;
  total_hours: string;
  vessel_remarks: string | null;
  shore_remarks: string | null;
  can_edit: boolean;
  can_delete: boolean;
}

export interface ExposureHoursRecordDetail extends ExposureHoursRecordRow {
  vessel_id: number | string;
  vessel: string;
}

export interface ExposureHoursRecordListResponse {
  columns: DashletColumn[];
  rows: ExposureHoursRecordRow[];
  meta: TableMeta | null;
}

export interface ExposureHoursOption {
  id: number | string;
  label: string;
}

export interface ExposureHoursOptions {
  vessels: ExposureHoursOption[];
  can_create_record: boolean;
}
