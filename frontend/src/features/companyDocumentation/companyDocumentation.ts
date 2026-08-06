import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface CompanyDocumentationOption {
  id: number | string;
  label: string;
}

export interface CompanyDocumentationTypeOptions {
  types: CompanyDocumentationOption[];
}

export interface CompanyDocumentationRow {
  id: number | string;
  document_type: string;
  document: string;
  doc_number: string | null;
  issuing_body: string | null;
  date_issued: string | null;
  date_expired: string | null;
  is_printer_friendly: boolean;
  /** 0 = fine, 1 = expiring soon, 2 = expired. */
  warning_status: 0 | 1 | 2;
  is_active: boolean;
  can_edit: boolean;
  can_delete: boolean;
}

export interface CompanyDocumentationListResponse {
  columns: DashletColumn[];
  rows: CompanyDocumentationRow[];
  meta: TableMeta;
}
