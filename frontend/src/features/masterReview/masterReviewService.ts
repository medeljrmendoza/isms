import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { MasterReviewDetail, MasterReviewListResponse, MasterReviewOptions } from "./masterReview";

export interface MasterReviewListParams {
  vessel_id?: number | string;
  start_quarter?: number;
  start_year?: number;
  end_quarter?: number;
  end_year?: number;
  record_status?: string;
  chapter_id?: number | string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

/** Read-only: Add/Edit/Approve/Disapprove/Disregard/Recommend Approval/Under Review/Reopen/Delete have no legacy write-back path — see MasterReviewPage. */
export const masterReviewService = {
  async options(): Promise<MasterReviewOptions> {
    const response = await axiosClient.get<ApiResource<MasterReviewOptions>>("/master-review/options");
    return response.data.data;
  },

  async list(params: MasterReviewListParams): Promise<MasterReviewListResponse> {
    const response = await axiosClient.get<ApiResource<MasterReviewListResponse>>("/master-review", { params });
    return response.data.data;
  },

  async show(id: number | string): Promise<MasterReviewDetail> {
    const response = await axiosClient.get<ApiResource<MasterReviewDetail>>(`/master-review/${id}`);
    return response.data.data;
  },
};
