import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  RiskAssessmentApprovalPayload,
  RiskAssessmentDetail,
  RiskAssessmentOptions,
  RiskAssessmentRow,
} from "./riskAssessment";

export interface RiskAssessmentListParams {
  vessel_id: number;
  year: number;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export interface RiskAssessmentListResponse {
  columns: { key: string; label: string; sortable: boolean }[];
  rows: RiskAssessmentRow[];
  meta: { current_page: number; last_page: number; per_page: number; total: number } | null;
}

export const riskAssessmentService = {
  async options(): Promise<RiskAssessmentOptions> {
    const response = await axiosClient.get<ApiResource<RiskAssessmentOptions>>("/risk-assessments/options");
    return response.data.data;
  },

  async list(params: RiskAssessmentListParams): Promise<RiskAssessmentListResponse> {
    const response = await axiosClient.get<ApiResource<RiskAssessmentListResponse>>("/risk-assessments", { params });
    return response.data.data;
  },

  async show(id: number): Promise<RiskAssessmentDetail> {
    const response = await axiosClient.get<ApiResource<RiskAssessmentDetail>>(`/risk-assessments/${id}`);
    return response.data.data;
  },

  async approveShore(id: number, payload: RiskAssessmentApprovalPayload): Promise<RiskAssessmentDetail> {
    const response = await axiosClient.post<ApiResource<RiskAssessmentDetail>>(`/risk-assessments/${id}/approve-shore`, payload);
    return response.data.data;
  },

  async approveMarine(id: number, payload: RiskAssessmentApprovalPayload): Promise<RiskAssessmentDetail> {
    const response = await axiosClient.post<ApiResource<RiskAssessmentDetail>>(`/risk-assessments/${id}/approve-marine`, payload);
    return response.data.data;
  },
};
