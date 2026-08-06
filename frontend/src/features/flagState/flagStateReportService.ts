import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  FlagStateReportDetail,
  FlagStateReportListResponse,
  FlagStateReportOptions,
} from "./flagState";

export interface FlagStateReportListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

/** Read-only: Add/Edit/Publish/Approve/Delete have no legacy write-back path — see FlagStateReportsPage. */
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
};
