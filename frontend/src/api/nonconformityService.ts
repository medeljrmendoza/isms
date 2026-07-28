import { axiosClient } from "./axiosClient";
import type { ApiResource } from "../types/auth";
import type {
  NonconformityDetail,
  NonconformityListResponse,
  NonconformityOptions,
} from "../types/nonconformity";
import type { NonconformityFormValues } from "../schemas/nonconformitySchema";

export interface NonconformityListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_company?: string;
  date_from?: string;
  date_to?: string;
}

export const nonconformityService = {
  async list(params: NonconformityListParams): Promise<NonconformityListResponse> {
    const response = await axiosClient.get<ApiResource<NonconformityListResponse>>("/nonconformities", { params });
    return response.data.data;
  },

  async options(): Promise<NonconformityOptions> {
    const response = await axiosClient.get<ApiResource<NonconformityOptions>>("/nonconformities/options");
    return response.data.data;
  },

  async show(id: number): Promise<NonconformityDetail> {
    const response = await axiosClient.get<ApiResource<NonconformityDetail>>(`/nonconformities/${id}`);
    return response.data.data;
  },

  async create(values: NonconformityFormValues): Promise<NonconformityDetail> {
    const response = await axiosClient.post<ApiResource<NonconformityDetail>>("/nonconformities", values);
    return response.data.data;
  },

  async update(id: number, values: NonconformityFormValues): Promise<NonconformityDetail> {
    const response = await axiosClient.put<ApiResource<NonconformityDetail>>(`/nonconformities/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/nonconformities/${id}`);
  },

  async publish(id: number): Promise<NonconformityDetail> {
    const response = await axiosClient.post<ApiResource<NonconformityDetail>>(`/nonconformities/${id}/publish`);
    return response.data.data;
  },

  async approve(id: number): Promise<NonconformityDetail> {
    const response = await axiosClient.post<ApiResource<NonconformityDetail>>(`/nonconformities/${id}/approve`);
    return response.data.data;
  },

  async reopen(id: number): Promise<NonconformityDetail> {
    const response = await axiosClient.post<ApiResource<NonconformityDetail>>(`/nonconformities/${id}/reopen`);
    return response.data.data;
  },
};
