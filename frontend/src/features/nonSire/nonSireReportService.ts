import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  NonSireReportDetail,
  NonSireReportListResponse,
  NonSireReportOptions,
} from "./nonSire";
import type { NonSireReportFormValues } from "./nonSireReportSchema";

export interface NonSireReportListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

export const nonSireReportService = {
  async list(params: NonSireReportListParams): Promise<NonSireReportListResponse> {
    const response = await axiosClient.get<ApiResource<NonSireReportListResponse>>("/non-sire-reports", { params });
    return response.data.data;
  },

  async options(): Promise<NonSireReportOptions> {
    const response = await axiosClient.get<ApiResource<NonSireReportOptions>>("/non-sire-reports/options");
    return response.data.data;
  },

  async show(id: number): Promise<NonSireReportDetail> {
    const response = await axiosClient.get<ApiResource<NonSireReportDetail>>(`/non-sire-reports/${id}`);
    return response.data.data;
  },

  async create(values: NonSireReportFormValues): Promise<NonSireReportDetail> {
    const response = await axiosClient.post<ApiResource<NonSireReportDetail>>("/non-sire-reports", values);
    return response.data.data;
  },

  async update(id: number, values: NonSireReportFormValues): Promise<NonSireReportDetail> {
    const response = await axiosClient.put<ApiResource<NonSireReportDetail>>(`/non-sire-reports/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/non-sire-reports/${id}`);
  },

  async publish(id: number): Promise<NonSireReportDetail> {
    const response = await axiosClient.post<ApiResource<NonSireReportDetail>>(`/non-sire-reports/${id}/publish`);
    return response.data.data;
  },

  async approve(id: number): Promise<NonSireReportDetail> {
    const response = await axiosClient.post<ApiResource<NonSireReportDetail>>(`/non-sire-reports/${id}/approve`);
    return response.data.data;
  },
};
