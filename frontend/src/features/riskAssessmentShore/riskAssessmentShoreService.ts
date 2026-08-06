import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { RiskAssessmentShoreDetail, RiskAssessmentShoreOptions, RiskAssessmentShoreRow } from "./riskAssessmentShore";

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

/** Read-only: Add/Edit/Delete/Reopen have no legacy write-back path — see RiskAssessmentShoreListPage. */
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
};
