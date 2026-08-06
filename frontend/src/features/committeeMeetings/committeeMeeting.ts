import type { DashletColumn, TableMeta } from "../dashboard/dashboard";

export interface CommitteeMeetingRow {
  /** A local numeric id normally, but a legacy meetingID string when reading from the legacy connection. */
  id: number | string;
  meeting_date: string;
  added_by: "SHORE" | "VESSEL";
  shore_vessel_meeting: "SHORE" | "VESSEL";
  vessel: string;
  meeting_type: string;
  chairman: string | null;
  incharge: string | null;
  has_shore_remarks: boolean;
  published: boolean | null;
  is_approved: boolean | null;
  can_edit: boolean;
  can_publish: boolean;
  can_approve: boolean;
  can_delete: boolean;
}

export interface CommitteeMeetingTypeDetail {
  committee_meeting_type_id: number;
  name: string;
  type_other: string | null;
}

export interface CommitteeMeetingPersonDetail {
  name: string;
}

export interface CommitteeMeetingTopicDetail {
  topic_name: string;
  meeting_details: string | null;
  meeting_comments: string | null;
}

export interface CommitteeMeetingDetail extends CommitteeMeetingRow {
  vessel_id: number | null;
  meeting_position: string | null;
  meeting_time: string | null;
  vessel_remarks: string | null;
  shore_remarks: string | null;
  meeting_types: CommitteeMeetingTypeDetail[];
  attendees: CommitteeMeetingPersonDetail[];
  members: CommitteeMeetingPersonDetail[];
  topics: CommitteeMeetingTopicDetail[];
}

export interface CommitteeMeetingListResponse {
  columns: DashletColumn[];
  rows: CommitteeMeetingRow[];
  meta: TableMeta;
}

export interface CommitteeMeetingOption {
  id: number | string;
  label: string;
}

export interface CommitteeMeetingOptions {
  vessels: CommitteeMeetingOption[];
}
