export interface PmsRunningHoursOption {
  id: number | string;
  label: string;
}

export interface PmsRunningHoursOptions {
  vessels: PmsRunningHoursOption[];
}

export interface PmsRunningHoursPeriod {
  month: number;
  year: number;
  label?: string;
}

export interface PmsRunningHoursRow {
  equipment_id: number | string;
  equipment_code: string;
  equipment_name: string;
  update_by_component: boolean;
  since_delivery: number | null;
  monthly_rh: number | null;
  daily_hours: Record<string, number>;
}

export interface PmsRunningHoursResponse {
  current_period: PmsRunningHoursPeriod | null;
  period_options: PmsRunningHoursPeriod[];
  rows: PmsRunningHoursRow[];
}

export interface PmsRunningHoursPartRow {
  part_id: string;
  part_code: string;
  part_name: string;
  since_delivery: number;
  since_last_activity: number;
  date_last_activity: string | null;
  date_last_reset: string | null;
  daily_hours: Record<string, number>;
}

export interface PmsRunningHoursPartsResponse {
  current_period: PmsRunningHoursPeriod | null;
  equipment_code: string | null;
  equipment_name: string | null;
  rows: PmsRunningHoursPartRow[];
}
