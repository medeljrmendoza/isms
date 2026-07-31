import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { MasterReviewDetail, MasterReviewListResponse, MasterReviewOption, MasterReviewOptions } from "./masterReview";
import type { MasterReviewFormValues } from "./masterReviewSchema";

export interface MasterReviewListParams {
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

export const masterReviewService = {
  async options(): Promise<MasterReviewOptions> {
    const response = await axiosClient.get<ApiResource<MasterReviewOptions>>("/master-review/options");
    return response.data.data;
  },

  async documentOptions(chapterId: number): Promise<MasterReviewOption[]> {
    const response = await axiosClient.get<ApiResource<MasterReviewOption[]>>("/master-review/document-options", {
      params: { chapter_id: chapterId },
    });
    return response.data.data;
  },

  async list(params: MasterReviewListParams): Promise<MasterReviewListResponse> {
    const response = await axiosClient.get<ApiResource<MasterReviewListResponse>>("/master-review", { params });
    return response.data.data;
  },

  async show(id: number): Promise<MasterReviewDetail> {
    const response = await axiosClient.get<ApiResource<MasterReviewDetail>>(`/master-review/${id}`);
    return response.data.data;
  },

  async create(values: MasterReviewFormValues): Promise<MasterReviewDetail> {
    const response = await axiosClient.post<ApiResource<MasterReviewDetail>>("/master-review", values);
    return response.data.data;
  },

  async update(id: number, values: MasterReviewFormValues): Promise<MasterReviewDetail> {
    const response = await axiosClient.put<ApiResource<MasterReviewDetail>>(`/master-review/${id}`, values);
    return response.data.data;
  },

  async approve(id: number): Promise<MasterReviewDetail> {
    const response = await axiosClient.post<ApiResource<MasterReviewDetail>>(`/master-review/${id}/approve`);
    return response.data.data;
  },

  async disapprove(id: number): Promise<MasterReviewDetail> {
    const response = await axiosClient.post<ApiResource<MasterReviewDetail>>(`/master-review/${id}/disapprove`);
    return response.data.data;
  },

  async disregard(id: number): Promise<MasterReviewDetail> {
    const response = await axiosClient.post<ApiResource<MasterReviewDetail>>(`/master-review/${id}/disregard`);
    return response.data.data;
  },

  async recommendApproval(id: number): Promise<MasterReviewDetail> {
    const response = await axiosClient.post<ApiResource<MasterReviewDetail>>(`/master-review/${id}/recommend-approval`);
    return response.data.data;
  },

  async underReview(id: number): Promise<MasterReviewDetail> {
    const response = await axiosClient.post<ApiResource<MasterReviewDetail>>(`/master-review/${id}/under-review`);
    return response.data.data;
  },

  async reopen(id: number): Promise<MasterReviewDetail> {
    const response = await axiosClient.post<ApiResource<MasterReviewDetail>>(`/master-review/${id}/reopen`);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/master-review/${id}`);
  },
};
