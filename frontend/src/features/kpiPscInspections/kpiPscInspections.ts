export type KpiPscFilter = "vessel" | "mou" | "nonconformities";

export interface KpiSummaryRow {
  label: string;
  count: number;
}

export interface KpiOption {
  id: number;
  label: string;
}

export interface KpiPscOptions {
  vessels: KpiOption[];
  mous: KpiOption[];
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
