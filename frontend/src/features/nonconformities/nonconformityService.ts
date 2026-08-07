import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  NonconformityDetail,
  NonconformityListResponse,
  NonconformityOptions,
} from "./nonconformity";
import type { NonconformityFormValues } from "./nonconformitySchema";

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

  async show(id: number | string): Promise<NonconformityDetail> {
    const response = await axiosClient.get<ApiResource<NonconformityDetail>>(`/nonconformities/${id}`);
    return response.data.data;
  },

  async create(values: NonconformityFormValues): Promise<NonconformityDetail> {
    const response = await axiosClient.post<ApiResource<NonconformityDetail>>("/nonconformities", values);
    return response.data.data;
  },

  async update(id: number | string, values: NonconformityFormValues): Promise<NonconformityDetail> {
    const response = await axiosClient.put<ApiResource<NonconformityDetail>>(`/nonconformities/${id}`, values);
    return response.data.data;
  },

  async toggleInactive(id: number | string): Promise<NonconformityDetail> {
    const response = await axiosClient.post<ApiResource<NonconformityDetail>>(`/nonconformities/${id}/toggle-inactive`);
    return response.data.data;
  },

  async togglePublish(id: number | string): Promise<NonconformityDetail> {
    const response = await axiosClient.post<ApiResource<NonconformityDetail>>(`/nonconformities/${id}/toggle-publish`);
    return response.data.data;
  },

  async approve(id: number | string): Promise<NonconformityDetail> {
    const response = await axiosClient.post<ApiResource<NonconformityDetail>>(`/nonconformities/${id}/approve`);
    return response.data.data;
  },

  async reopen(id: number | string): Promise<NonconformityDetail> {
    const response = await axiosClient.post<ApiResource<NonconformityDetail>>(`/nonconformities/${id}/reopen`);
    return response.data.data;
  },

  async remove(id: number | string): Promise<NonconformityDetail> {
    const response = await axiosClient.delete<ApiResource<NonconformityDetail>>(`/nonconformities/${id}`);
    return response.data.data;
  },
};
