export interface KpiSummaryRow {
  label: string;
  count: number;
}

export interface KpiOption {
  /** A local numeric id normally, but a legacy string id when reading from the legacy connection. */
  id: number | string;
  label: string;
}

export interface KpiColumn {
  key: string;
  label: string;
  sortable: boolean;
}

export interface KpiListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface KpiListResponse {
  columns: KpiColumn[];
  rows: Record<string, string | number | null>[];
  meta: KpiListMeta;
}

export interface DateRangeParams {
  from?: string;
  to?: string;
}

export interface DrillDownParams extends DateRangeParams {
  page: number;
  per_page: number;
  sort?: string;
  direction?: "asc" | "desc";
}
