import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  FlagStateReportDetail,
  FlagStateReportListResponse,
  FlagStateReportOptions,
} from "./flagState";
import type { FlagStateReportFormValues } from "./flagStateReportSchema";

export interface FlagStateReportListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

export const flagStateReportService = {
  async list(params: FlagStateReportListParams): Promise<FlagStateReportListResponse> {
    const response = await axiosClient.get<ApiResource<FlagStateReportListResponse>>("/flag-state-reports", { params });
    return response.data.data;
  },

  async options(): Promise<FlagStateReportOptions> {
    const response = await axiosClient.get<ApiResource<FlagStateReportOptions>>("/flag-state-reports/options");
    return response.data.data;
  },

  async show(id: number | string): Promise<FlagStateReportDetail> {
    const response = await axiosClient.get<ApiResource<FlagStateReportDetail>>(`/flag-state-reports/${id}`);
    return response.data.data;
  },

  async create(values: FlagStateReportFormValues): Promise<FlagStateReportDetail> {
    const response = await axiosClient.post<ApiResource<FlagStateReportDetail>>("/flag-state-reports", values);
    return response.data.data;
  },

  async update(id: number, values: FlagStateReportFormValues): Promise<FlagStateReportDetail> {
    const response = await axiosClient.put<ApiResource<FlagStateReportDetail>>(`/flag-state-reports/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/flag-state-reports/${id}`);
  },

  async publish(id: number): Promise<FlagStateReportDetail> {
    const response = await axiosClient.post<ApiResource<FlagStateReportDetail>>(`/flag-state-reports/${id}/publish`);
    return response.data.data;
  },

  async approve(id: number): Promise<FlagStateReportDetail> {
    const response = await axiosClient.post<ApiResource<FlagStateReportDetail>>(`/flag-state-reports/${id}/approve`);
    return response.data.data;
  },
};
