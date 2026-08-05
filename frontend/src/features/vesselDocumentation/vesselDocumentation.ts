import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface VesselDocumentationOption {
  id: number | string;
  label: string;
}

export interface VesselDocumentationOptions {
  vessels: VesselDocumentationOption[];
  can_create_record: boolean;
}

export interface VesselDocumentationRow {
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

export interface VesselDocumentationDetail extends VesselDocumentationRow {
  vessel_id: number | string;
  vessel_document_id: number | string;
  date_range_from: string | null;
  date_range_to: string | null;
  shore_remarks: string | null;
  vessel_remarks: string | null;
}

export interface VesselDocumentationListResponse {
  columns: DashletColumn[];
  rows: VesselDocumentationRow[];
  meta: TableMeta;
}
