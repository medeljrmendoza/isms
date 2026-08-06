export interface PmsDepartmentRow {
  id: number | string;
  name: string;
  is_active: boolean;
  can_edit: boolean;
}

export interface PmsDepartmentListResponse {
  rows: PmsDepartmentRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  can_create_record: boolean;
}
