import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { PscReportDetail, PscReportListResponse, PscReportOptions } from "./pscReport";
import type { PscReportFormValues } from "./pscReportSchema";

export interface PscReportListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

/** No reopen(): legacy's own Reopen button is dead code — see PscReportRepository. */
export const pscReportService = {
  async list(params: PscReportListParams): Promise<PscReportListResponse> {
    const response = await axiosClient.get<ApiResource<PscReportListResponse>>("/psc-reports", { params });
    return response.data.data;
  },

  async options(): Promise<PscReportOptions> {
    const response = await axiosClient.get<ApiResource<PscReportOptions>>("/psc-reports/options");
    return response.data.data;
  },

  async show(id: string): Promise<PscReportDetail> {
    const response = await axiosClient.get<ApiResource<PscReportDetail>>(`/psc-reports/${id}`);
    return response.data.data;
  },

  async create(values: PscReportFormValues): Promise<PscReportDetail> {
    const response = await axiosClient.post<ApiResource<PscReportDetail>>("/psc-reports", values);
    return response.data.data;
  },

  async update(id: string, values: PscReportFormValues): Promise<PscReportDetail> {
    const response = await axiosClient.put<ApiResource<PscReportDetail>>(`/psc-reports/${id}`, values);
    return response.data.data;
  },

  async destroy(id: string): Promise<void> {
    await axiosClient.delete(`/psc-reports/${id}`);
  },
};
