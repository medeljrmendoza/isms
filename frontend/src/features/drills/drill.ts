export type DrillStatus = "overdue" | "upcoming" | null;

export interface DrillGridRow {
  /** A local numeric drill_list id normally, but a legacy drillListID string when reading from the legacy connection. */
  id: number | string;
  drill_type: string | null;
  name: string;
  frequency: string;
  last_drill: string | null;
  next_drill: string | null;
  status: DrillStatus;
  months: Record<string, Array<{ id: number | string; day: number }>>;
}

export interface DrillCalendarResponse {
  rows: DrillGridRow[];
  year: number;
}

export interface DrillCellItem {
  /** A local numeric id normally, but a legacy drillID string when reading from the legacy connection. */
  id: number | string;
  drill_date: string;
  drill_position: string | null;
  drill_time_from: string | null;
  can_edit: boolean;
}

export interface DrillCrewMember {
  crew_name: string;
}

export interface DrillReportDetail {
  /** A local numeric id normally, but a legacy drillID string when reading from the legacy connection. */
  id: number | string;
  vessel: string;
  drill_list_id: number | string;
  drill_name: string;
  drill_type: string | null;
  master_name: string | null;
  drill_date: string | null;
  drill_time_from: string | null;
  drill_position: string | null;
  drill_details: string | null;
  drill_deficiencies: string | null;
  drill_corrective_action: string | null;
  report_date: string | null;
  vessel_remarks: string | null;
  receipt_date: string | null;
  shore_remarks: string | null;
  can_edit: boolean;
  crew: DrillCrewMember[];
}

export interface DrillOption {
  id: number | string;
  label: string;
}

export interface DrillOptions {
  vessels: DrillOption[];
  years: number[];
}
