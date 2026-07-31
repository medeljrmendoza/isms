export interface RevisionHistoryOption {
  id: number;
  label: string;
}

export interface RevisionHistoryOptions {
  chapters: RevisionHistoryOption[];
}

export interface RevisionHistoryColumn {
  key: string;
  label: string;
  sortable: boolean;
}

export interface RevisionHistoryRow {
  id: number;
  arrangement: number;
  date_revised: string;
  revision_no: string;
  reference_no: string;
  section: string | null;
  reason_revision: string | null;
  reviewed_by: string;
  approved_by: string;
}

export interface RevisionHistoryDetail extends RevisionHistoryRow {
  manual_chapter_id: number | null;
  manual_document_id: number;
  procedure_label: string;
}

export interface RevisionHistoryListResponse {
  columns: RevisionHistoryColumn[];
  rows: RevisionHistoryRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
