import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  SireReportDetail,
  SireReportListResponse,
  SireReportOptions,
} from "./sire";
import type { SireReportFormValues } from "./sireReportSchema";

export interface SireReportListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

export const sireReportService = {
  async list(params: SireReportListParams): Promise<SireReportListResponse> {
    const response = await axiosClient.get<ApiResource<SireReportListResponse>>("/sire-reports", { params });
    return response.data.data;
  },

  async options(): Promise<SireReportOptions> {
    const response = await axiosClient.get<ApiResource<SireReportOptions>>("/sire-reports/options");
    return response.data.data;
  },

  async show(id: number): Promise<SireReportDetail> {
    const response = await axiosClient.get<ApiResource<SireReportDetail>>(`/sire-reports/${id}`);
    return response.data.data;
  },

  async create(values: SireReportFormValues): Promise<SireReportDetail> {
    const response = await axiosClient.post<ApiResource<SireReportDetail>>("/sire-reports", values);
    return response.data.data;
  },

  async update(id: number, values: SireReportFormValues): Promise<SireReportDetail> {
    const response = await axiosClient.put<ApiResource<SireReportDetail>>(`/sire-reports/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/sire-reports/${id}`);
  },

  async publish(id: number): Promise<SireReportDetail> {
    const response = await axiosClient.post<ApiResource<SireReportDetail>>(`/sire-reports/${id}/publish`);
    return response.data.data;
  },

  async approve(id: number): Promise<SireReportDetail> {
    const response = await axiosClient.post<ApiResource<SireReportDetail>>(`/sire-reports/${id}/approve`);
    return response.data.data;
  },
};
