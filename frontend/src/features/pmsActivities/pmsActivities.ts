export interface PmsActivityOption {
  id: number | string;
  label: string;
}

export interface PmsActivityOptions {
  vessels: PmsActivityOption[];
  departments: PmsActivityOption[];
  criticalities: PmsActivityOption[];
  main_groups: PmsActivityOption[];
}

export interface PmsActivityMonthCell {
  day: number;
  ticket_no: string;
  is_overdue?: boolean;
}

export interface PmsActivityMonth {
  done: PmsActivityMonthCell | null;
  postponed: { day: number; ticket_no: string } | null;
}

export type PmsActivityStatus = "overdue" | "upcoming" | "postponed" | null;

export interface PmsActivityRow {
  id: number | string;
  is_snapshot: boolean;
  main_group: string | null;
  department: string | null;
  activity_code: string | null;
  activity_name: string;
  criticality: string | null;
  equipment_name: string | null;
  part_name: string | null;
  incharge: string | null;
  frequency: string | null;
  total_hours: string | null;
  last_done: string | null;
  due_date: string | null;
  status: PmsActivityStatus;
  months: Record<number, PmsActivityMonth>;
}

export interface PmsActivityDetail {
  id: number | string;
  vessel: string;
  activity_code: string | null;
  activity_name: string;
  equipment_name: string | null;
  part_name: string | null;
  department: string | null;
  main_group: string | null;
  criticality: string | null;
  incharge: string | null;
  work_procedure: string | null;
  frequency: string | null;
  last_done: string | null;
  due_date: string | null;
  is_overdue: boolean;
  is_postponed: boolean;
  postpone_date: string | null;
  is_running_hours_tracked: boolean;
}

export interface PmsTicketDetail {
  ticket_no: string;
  type: "PLANNED" | "UNPLANNED" | "POSTPONED";
  vessel: string;
  activity_name: string;
  date_of_activity: string;
  description: string | null;
  possible_cause: string | null;
  remarks: string | null;
  incharge: string | null;
  frequency: string | null;
  is_overdue: boolean | null;
  equipment_name: string | null;
  part_name: string | null;
  previous_last_done: string | null;
  previous_due_date: string | null;
  reported_by: string | null;
  created_at: string;
}

export interface PmsActivitiesResponse {
  current_period: { month: number; year: number } | null;
  year_options: number[];
  rows: PmsActivityRow[];
}

export const MONTH_LABELS = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
