import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  RevisionHistoryListResponse,
  RevisionHistoryOptions,
} from "./revisionHistory";

export interface RevisionHistoryListParams {
  chapter_id?: number | string;
  date_from?: string;
  date_to?: string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

/** Read-only: Add/Edit/Delete have no legacy write-back path — see RevisionHistoryPage. */
export const revisionHistoryService = {
  async options(): Promise<RevisionHistoryOptions> {
    const response = await axiosClient.get<ApiResource<RevisionHistoryOptions>>("/revision-history/options");
    return response.data.data;
  },

  async list(params: RevisionHistoryListParams): Promise<RevisionHistoryListResponse> {
    const response = await axiosClient.get<ApiResource<RevisionHistoryListResponse>>("/revision-history", { params });
    return response.data.data;
  },
};
