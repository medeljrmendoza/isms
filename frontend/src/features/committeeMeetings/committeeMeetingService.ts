import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  CommitteeMeetingDetail,
  CommitteeMeetingListResponse,
  CommitteeMeetingOptions,
} from "./committeeMeeting";
import type { CommitteeMeetingFormValues } from "./committeeMeetingSchema";

export interface CommitteeMeetingListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

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

  async create(values: CommitteeMeetingFormValues): Promise<CommitteeMeetingDetail> {
    const response = await axiosClient.post<ApiResource<CommitteeMeetingDetail>>("/committee-meetings", values);
    return response.data.data;
  },

  async update(id: number, values: CommitteeMeetingFormValues): Promise<CommitteeMeetingDetail> {
    const response = await axiosClient.put<ApiResource<CommitteeMeetingDetail>>(`/committee-meetings/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/committee-meetings/${id}`);
  },

  async publish(id: number): Promise<CommitteeMeetingDetail> {
    const response = await axiosClient.post<ApiResource<CommitteeMeetingDetail>>(`/committee-meetings/${id}/publish`);
    return response.data.data;
  },

  async approve(id: number): Promise<CommitteeMeetingDetail> {
    const response = await axiosClient.post<ApiResource<CommitteeMeetingDetail>>(`/committee-meetings/${id}/approve`);
    return response.data.data;
  },
};
