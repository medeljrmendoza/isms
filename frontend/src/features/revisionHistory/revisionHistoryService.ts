import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  RevisionHistoryDetail,
  RevisionHistoryListResponse,
  RevisionHistoryOption,
  RevisionHistoryOptions,
} from "./revisionHistory";
import type { RevisionHistoryFormValues } from "./revisionHistorySchema";

export interface RevisionHistoryListParams {
  chapter_id?: number;
  date_from?: string;
  date_to?: string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export const revisionHistoryService = {
  async options(): Promise<RevisionHistoryOptions> {
    const response = await axiosClient.get<ApiResource<RevisionHistoryOptions>>("/revision-history/options");
    return response.data.data;
  },

  async documentOptions(chapterId: number): Promise<RevisionHistoryOption[]> {
    const response = await axiosClient.get<ApiResource<RevisionHistoryOption[]>>("/revision-history/document-options", {
      params: { chapter_id: chapterId },
    });
    return response.data.data;
  },

  async list(params: RevisionHistoryListParams): Promise<RevisionHistoryListResponse> {
    const response = await axiosClient.get<ApiResource<RevisionHistoryListResponse>>("/revision-history", { params });
    return response.data.data;
  },

  async show(id: number): Promise<RevisionHistoryDetail> {
    const response = await axiosClient.get<ApiResource<RevisionHistoryDetail>>(`/revision-history/${id}`);
    return response.data.data;
  },

  async create(values: RevisionHistoryFormValues): Promise<RevisionHistoryDetail> {
    const response = await axiosClient.post<ApiResource<RevisionHistoryDetail>>("/revision-history", values);
    return response.data.data;
  },

  async update(id: number, values: RevisionHistoryFormValues): Promise<RevisionHistoryDetail> {
    const response = await axiosClient.put<ApiResource<RevisionHistoryDetail>>(`/revision-history/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/revision-history/${id}`);
  },
};
