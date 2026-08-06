export interface PmsDoneActivityOption {
  id: number | string;
  label: string;
}

export interface PmsDoneActivityOptions {
  vessels: PmsDoneActivityOption[];
}

export interface PmsDoneActivityColumn {
  key: string;
  label: string;
  sortable: boolean;
}

export interface PmsDoneActivityRow {
  id: number | string;
  ticket_no: string;
  date_of_activity: string;
  previous_due_date: string | null;
  previous_last_done: string | null;
  equipment_name: string | null;
  part_name: string | null;
  activity_code: string | null;
  activity_name: string;
  frequency: string | null;
  incharge: string | null;
  reported_by: string | null;
  created_at: string;
}

export interface PmsDoneActivitiesListResponse {
  columns: PmsDoneActivityColumn[];
  rows: PmsDoneActivityRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
