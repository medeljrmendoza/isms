import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { RiskAssessmentShoreDetail, RiskAssessmentShoreOptions, RiskAssessmentShoreRow } from "./riskAssessmentShore";
import type { RiskAssessmentShoreFormValues } from "./riskAssessmentShoreSchema";

export interface RiskAssessmentShoreListParams {
  vessel_id?: number | string;
  year?: number;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export interface RiskAssessmentShoreListResponse {
  columns: { key: string; label: string; sortable: boolean }[];
  rows: RiskAssessmentShoreRow[];
  meta: { current_page: number; last_page: number; per_page: number; total: number } | null;
}

export const riskAssessmentShoreService = {
  async options(): Promise<RiskAssessmentShoreOptions> {
    const response = await axiosClient.get<ApiResource<RiskAssessmentShoreOptions>>("/risk-assessments-shore/options");
    return response.data.data;
  },

  async list(params: RiskAssessmentShoreListParams): Promise<RiskAssessmentShoreListResponse> {
    const response = await axiosClient.get<ApiResource<RiskAssessmentShoreListResponse>>("/risk-assessments-shore", { params });
    return response.data.data;
  },

  async show(id: number | string): Promise<RiskAssessmentShoreDetail> {
    const response = await axiosClient.get<ApiResource<RiskAssessmentShoreDetail>>(`/risk-assessments-shore/${id}`);
    return response.data.data;
  },

  async create(values: RiskAssessmentShoreFormValues): Promise<RiskAssessmentShoreDetail> {
    const response = await axiosClient.post<ApiResource<RiskAssessmentShoreDetail>>("/risk-assessments-shore", values);
    return response.data.data;
  },

  async update(id: number, values: RiskAssessmentShoreFormValues): Promise<RiskAssessmentShoreDetail> {
    const response = await axiosClient.put<ApiResource<RiskAssessmentShoreDetail>>(`/risk-assessments-shore/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/risk-assessments-shore/${id}`);
  },

  async reopen(id: number): Promise<RiskAssessmentShoreDetail> {
    const response = await axiosClient.post<ApiResource<RiskAssessmentShoreDetail>>(`/risk-assessments-shore/${id}/reopen`);
    return response.data.data;
  },
};
