import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  CommitteeMeetingDetail,
  CommitteeMeetingListResponse,
  CommitteeMeetingOptions,
} from "./committeeMeeting";

export interface CommitteeMeetingListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

/** Read-only: Add/Edit/Publish/Approve/Delete have no legacy write-back path — see CommitteeMeetingsPage. */
export const committeeMeetingService = {
  async list(params: CommitteeMeetingListParams): Promise<CommitteeMeetingListResponse> {
    const response = await axiosClient.get<ApiResource<CommitteeMeetingListResponse>>("/committee-meetings", { params });
    return response.data.data;
  },

  async options(): Promise<CommitteeMeetingOptions> {
    const response = await axiosClient.get<ApiResource<CommitteeMeetingOptions>>("/committee-meetings/options");
    return response.data.data;
  },

  async show(id: number | string): Promise<CommitteeMeetingDetail> {
    const response = await axiosClient.get<ApiResource<CommitteeMeetingDetail>>(`/committee-meetings/${id}`);
    return response.data.data;
  },
};
