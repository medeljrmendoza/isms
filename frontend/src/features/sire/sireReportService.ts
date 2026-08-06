import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  SireReportDetail,
  SireReportListResponse,
  SireReportOptions,
} from "./sire";

export interface SireReportListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

/** Read-only: Add/Edit/Publish/Approve/Delete have no legacy write-back path — see SireReportsPage. */
export const sireReportService = {
  async list(params: SireReportListParams): Promise<SireReportListResponse> {
    const response = await axiosClient.get<ApiResource<SireReportListResponse>>("/sire-reports", { params });
    return response.data.data;
  },

  async options(): Promise<SireReportOptions> {
    const response = await axiosClient.get<ApiResource<SireReportOptions>>("/sire-reports/options");
    return response.data.data;
  },

  async show(id: number | string): Promise<SireReportDetail> {
    const response = await axiosClient.get<ApiResource<SireReportDetail>>(`/sire-reports/${id}`);
    return response.data.data;
  },
};
