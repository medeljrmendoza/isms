export interface DashletItem {
  label: string;
  meta: string;
}

export interface DashletColumn {
  key: string;
  label: string;
  sortable: boolean;
}

export interface Dashlet {
  key: string;
  title: string;
  span: "full" | "half";
  manual_load: boolean;
  extra_action: "add_task" | null;
  items: DashletItem[];
  /** Present only for dashlets backed by a real, paginated table endpoint. */
  columns: DashletColumn[] | null;
  endpoint: string | null;
}

export interface DashboardData {
  dashlets: Dashlet[];
}

export type TableRow = Record<string, string | number>;

export interface TableMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface TableResponse {
  columns: DashletColumn[];
  rows: TableRow[];
  meta: TableMeta;
}
