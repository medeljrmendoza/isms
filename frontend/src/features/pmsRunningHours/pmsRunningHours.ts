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
