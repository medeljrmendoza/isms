import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  NonSireReportDetail,
  NonSireReportListResponse,
  NonSireReportOptions,
} from "./nonSire";

export interface NonSireReportListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

/** Read-only: Add/Edit/Publish/Approve/Delete have no legacy write-back path — see NonSireReportsPage. */
export const nonSireReportService = {
  async list(params: NonSireReportListParams): Promise<NonSireReportListResponse> {
    const response = await axiosClient.get<ApiResource<NonSireReportListResponse>>("/non-sire-reports", { params });
    return response.data.data;
  },

  async options(): Promise<NonSireReportOptions> {
    const response = await axiosClient.get<ApiResource<NonSireReportOptions>>("/non-sire-reports/options");
    return response.data.data;
  },

  async show(id: number | string): Promise<NonSireReportDetail> {
    const response = await axiosClient.get<ApiResource<NonSireReportDetail>>(`/non-sire-reports/${id}`);
    return response.data.data;
  },
};
