import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  ExternalAuditDetail,
  ExternalAuditListResponse,
  ExternalAuditOptions,
} from "./externalAudit";
import type { ExternalAuditFormValues } from "./externalAuditSchema";

export interface ExternalAuditListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

export const externalAuditService = {
  async list(params: ExternalAuditListParams): Promise<ExternalAuditListResponse> {
    const response = await axiosClient.get<ApiResource<ExternalAuditListResponse>>("/external-audits", { params });
    return response.data.data;
  },

  async options(): Promise<ExternalAuditOptions> {
    const response = await axiosClient.get<ApiResource<ExternalAuditOptions>>("/external-audits/options");
    return response.data.data;
  },

  async show(id: number | string): Promise<ExternalAuditDetail> {
    const response = await axiosClient.get<ApiResource<ExternalAuditDetail>>(`/external-audits/${id}`);
    return response.data.data;
  },

  async create(values: ExternalAuditFormValues): Promise<ExternalAuditDetail> {
    const response = await axiosClient.post<ApiResource<ExternalAuditDetail>>("/external-audits", values);
    return response.data.data;
  },

  async update(id: number, values: ExternalAuditFormValues): Promise<ExternalAuditDetail> {
    const response = await axiosClient.put<ApiResource<ExternalAuditDetail>>(`/external-audits/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/external-audits/${id}`);
  },

  async publish(id: number): Promise<ExternalAuditDetail> {
    const response = await axiosClient.post<ApiResource<ExternalAuditDetail>>(`/external-audits/${id}/publish`);
    return response.data.data;
  },

  async approve(id: number): Promise<ExternalAuditDetail> {
    const response = await axiosClient.post<ApiResource<ExternalAuditDetail>>(`/external-audits/${id}/approve`);
    return response.data.data;
  },
};
