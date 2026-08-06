import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { PscReportDetail, PscReportListResponse, PscReportOptions } from "./pscReport";

export interface PscReportListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

/** Read-only: Add/Edit/Delete/Reopen have no legacy write-back path — see PscReportsPage. */
export const pscReportService = {
  async list(params: PscReportListParams): Promise<PscReportListResponse> {
    const response = await axiosClient.get<ApiResource<PscReportListResponse>>("/psc-reports", { params });
    return response.data.data;
  },

  async options(): Promise<PscReportOptions> {
    const response = await axiosClient.get<ApiResource<PscReportOptions>>("/psc-reports/options");
    return response.data.data;
  },

  async show(id: number | string): Promise<PscReportDetail> {
    const response = await axiosClient.get<ApiResource<PscReportDetail>>(`/psc-reports/${id}`);
    return response.data.data;
  },
};
