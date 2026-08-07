import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  IncidentReportDetail,
  IncidentReportListResponse,
  IncidentReportOptions,
} from "./incidentReport";
import type { IncidentReportFormValues } from "./incidentReportSchema";

export interface IncidentReportListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
  year?: string;
}

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

  async create(values: IncidentReportFormValues): Promise<IncidentReportDetail> {
    const response = await axiosClient.post<ApiResource<IncidentReportDetail>>("/incident-reports", values);
    return response.data.data;
  },

  async update(id: number | string, values: IncidentReportFormValues): Promise<IncidentReportDetail> {
    const response = await axiosClient.put<ApiResource<IncidentReportDetail>>(`/incident-reports/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number | string): Promise<void> {
    await axiosClient.delete(`/incident-reports/${id}`);
  },

  async publish(id: number | string): Promise<IncidentReportDetail> {
    const response = await axiosClient.post<ApiResource<IncidentReportDetail>>(`/incident-reports/${id}/publish`);
    return response.data.data;
  },

  async approve(id: number | string): Promise<IncidentReportDetail> {
    const response = await axiosClient.post<ApiResource<IncidentReportDetail>>(`/incident-reports/${id}/approve`);
    return response.data.data;
  },

  async reopen(id: number | string): Promise<IncidentReportDetail> {
    const response = await axiosClient.post<ApiResource<IncidentReportDetail>>(`/incident-reports/${id}/reopen`);
    return response.data.data;
  },
};
