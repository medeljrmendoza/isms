export interface RevisionHistoryOption {
  /** A local numeric id normally, but a legacy string id when reading from the legacy connection. */
  id: number | string;
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
  /** A local numeric id normally, but a legacy string id when reading from the legacy connection. */
  id: number | string;
  arrangement: number;
  date_revised: string;
  revision_no: string;
  reference_no: string;
  section: string | null;
  reason_revision: string | null;
  reviewed_by: string;
  approved_by: string;
  can_edit: boolean;
  can_delete: boolean;
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
