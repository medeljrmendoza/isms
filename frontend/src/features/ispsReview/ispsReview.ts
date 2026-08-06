export interface IspsReviewOption {
  /** A local numeric id normally, but a legacy string id when reading from the legacy connection. */
  id: number | string;
  label: string;
}

export interface IspsReviewOptions {
  vessels: IspsReviewOption[];
  chapters: IspsReviewOption[];
}

export interface IspsReviewPresentRow {
  id?: number;
  name: string;
  position: string | null;
}

export interface IspsReviewRow {
  /** A local numeric id normally, but a legacy string id when reading from the legacy connection. */
  id: number | string;
  vessel: string;
  review_date: string;
  added_by: "SHORE" | "VESSEL";
  review_quarter: number;
  review_year: number;
  sms: string;
  review_recommendation: string | null;
  has_vessel_remarks: boolean;
  has_shore_remarks: boolean;
  shore_status: string;
  can_edit: boolean;
  can_approve: boolean;
  can_recommend_approval: boolean;
  can_disapprove: boolean;
  can_disregard: boolean;
  can_delete: boolean;
  can_reopen: boolean;
}

export interface IspsReviewDetail extends IspsReviewRow {
  manual_chapter_id: number | null;
  manual_document_id: number | null;
  manual_section: string | null;
  review_description: string | null;
  shore_reviewed_by: string | null;
  shore_remarks: string | null;
  vessel_reviewed_by: string | null;
  vessel_reviewed_position: string | null;
  vessel_remarks: string | null;
  present: IspsReviewPresentRow[];
}

export interface IspsReviewListResponse {
  columns: { key: string; label: string; sortable: boolean }[];
  rows: IspsReviewRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
