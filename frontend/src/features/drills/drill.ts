export type DrillStatus = "overdue" | "upcoming" | null;

export interface DrillGridRow {
  id: number;
  drill_type: string | null;
  name: string;
  frequency: string;
  last_drill: string | null;
  next_drill: string | null;
  status: DrillStatus;
  months: Record<string, Array<{ id: number; day: number }>>;
}

export interface DrillCalendarResponse {
  rows: DrillGridRow[];
  year: number;
}

export interface DrillCellItem {
  id: number;
  drill_date: string;
  drill_position: string | null;
  drill_time_from: string | null;
}

export interface DrillCrewMember {
  crew_name: string;
}

export interface DrillReportDetail {
  id: number;
  vessel: string;
  drill_list_id: number;
  drill_name: string;
  drill_type: string | null;
  master_name: string | null;
  drill_date: string;
  drill_time_from: string | null;
  drill_position: string | null;
  drill_details: string | null;
  drill_deficiencies: string | null;
  drill_corrective_action: string | null;
  report_date: string | null;
  vessel_remarks: string | null;
  receipt_date: string | null;
  shore_remarks: string | null;
  crew: DrillCrewMember[];
}

export interface DrillOption {
  id: number;
  label: string;
}

export interface DrillOptions {
  vessels: DrillOption[];
  years: number[];
}
