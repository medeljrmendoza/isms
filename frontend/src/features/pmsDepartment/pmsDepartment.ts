export interface PmsDepartmentRow {
  id: number;
  name: string;
  is_active: boolean;
}

export interface PmsDepartmentListResponse {
  rows: PmsDepartmentRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
