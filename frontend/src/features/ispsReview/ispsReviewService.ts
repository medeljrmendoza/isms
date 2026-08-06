import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { IspsReviewDetail, IspsReviewListResponse, IspsReviewOptions } from "./ispsReview";

export interface IspsReviewListParams {
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

/** Read-only: Add/Edit/Approve/Disapprove/Disregard/Recommend Approval/Reopen/Delete have no legacy write-back path — see IspsReviewPage. */
export const ispsReviewService = {
  async options(): Promise<IspsReviewOptions> {
    const response = await axiosClient.get<ApiResource<IspsReviewOptions>>("/isps-review/options");
    return response.data.data;
  },

  async list(params: IspsReviewListParams): Promise<IspsReviewListResponse> {
    const response = await axiosClient.get<ApiResource<IspsReviewListResponse>>("/isps-review", { params });
    return response.data.data;
  },

  async show(id: number | string): Promise<IspsReviewDetail> {
    const response = await axiosClient.get<ApiResource<IspsReviewDetail>>(`/isps-review/${id}`);
    return response.data.data;
  },
};
