import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { IspsReviewDetail, IspsReviewListResponse, IspsReviewOption, IspsReviewOptions } from "./ispsReview";
import type { IspsReviewFormValues } from "./ispsReviewSchema";

export interface IspsReviewListParams {
  vessel_id?: number;
  start_quarter?: number;
  start_year?: number;
  end_quarter?: number;
  end_year?: number;
  record_status?: string;
  chapter_id?: number;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export const ispsReviewService = {
  async options(): Promise<IspsReviewOptions> {
    const response = await axiosClient.get<ApiResource<IspsReviewOptions>>("/isps-review/options");
    return response.data.data;
  },

  async documentOptions(chapterId: number): Promise<IspsReviewOption[]> {
    const response = await axiosClient.get<ApiResource<IspsReviewOption[]>>("/isps-review/document-options", {
      params: { chapter_id: chapterId },
    });
    return response.data.data;
  },

  async list(params: IspsReviewListParams): Promise<IspsReviewListResponse> {
    const response = await axiosClient.get<ApiResource<IspsReviewListResponse>>("/isps-review", { params });
    return response.data.data;
  },

  async show(id: number): Promise<IspsReviewDetail> {
    const response = await axiosClient.get<ApiResource<IspsReviewDetail>>(`/isps-review/${id}`);
    return response.data.data;
  },

  async create(values: IspsReviewFormValues): Promise<IspsReviewDetail> {
    const response = await axiosClient.post<ApiResource<IspsReviewDetail>>("/isps-review", values);
    return response.data.data;
  },

  async update(id: number, values: IspsReviewFormValues): Promise<IspsReviewDetail> {
    const response = await axiosClient.put<ApiResource<IspsReviewDetail>>(`/isps-review/${id}`, values);
    return response.data.data;
  },

  async approve(id: number): Promise<IspsReviewDetail> {
    const response = await axiosClient.post<ApiResource<IspsReviewDetail>>(`/isps-review/${id}/approve`);
    return response.data.data;
  },

  async disapprove(id: number): Promise<IspsReviewDetail> {
    const response = await axiosClient.post<ApiResource<IspsReviewDetail>>(`/isps-review/${id}/disapprove`);
    return response.data.data;
  },

  async disregard(id: number): Promise<IspsReviewDetail> {
    const response = await axiosClient.post<ApiResource<IspsReviewDetail>>(`/isps-review/${id}/disregard`);
    return response.data.data;
  },

  async recommendApproval(id: number): Promise<IspsReviewDetail> {
    const response = await axiosClient.post<ApiResource<IspsReviewDetail>>(`/isps-review/${id}/recommend-approval`);
    return response.data.data;
  },

  async reopen(id: number): Promise<IspsReviewDetail> {
    const response = await axiosClient.post<ApiResource<IspsReviewDetail>>(`/isps-review/${id}/reopen`);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/isps-review/${id}`);
  },
};
