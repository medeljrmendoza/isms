import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  IncidentReportDetail,
  IncidentReportListResponse,
  IncidentReportOptions,
} from "./incidentReport";

export interface IncidentReportListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
  year?: string;
}

/** Read-only: Add/Edit/Publish/Approve/Delete/Reopen have no legacy write-back path — see IncidentReportsPage. */
export const incidentReportService = {
  async list(params: IncidentReportListParams): Promise<IncidentReportListResponse> {
    const response = await axiosClient.get<ApiResource<IncidentReportListResponse>>("/incident-reports", { params });
    return response.data.data;
  },

  async options(): Promise<IncidentReportOptions> {
    const response = await axiosClient.get<ApiResource<IncidentReportOptions>>("/incident-reports/options");
    return response.data.data;
  },

  async show(id: number | string): Promise<IncidentReportDetail> {
    const response = await axiosClient.get<ApiResource<IncidentReportDetail>>(`/incident-reports/${id}`);
    return response.data.data;
  },
};
