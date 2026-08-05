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

/** One row of the "Pending Items" dashlet's per-vessel category matrix. */
export interface PendingItemsRow {
  vessel_id: string;
  vessel: string;
  incident: number;
  company: number;
  internal: number;
  external: number;
  psc: number;
  risk_assessment: number;
  sire: number;
  non_sire: number;
  flag_state: number;
  nc: number;
  defect: number;
  master_review: number;
  isps_review: number;
}
