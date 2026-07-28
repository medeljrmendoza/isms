import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  InternalAuditDetail,
  InternalAuditListResponse,
  InternalAuditOptions,
} from "./internalAudit";
import type { InternalAuditFormValues } from "./internalAuditSchema";

export interface InternalAuditListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

export const internalAuditService = {
  async list(params: InternalAuditListParams): Promise<InternalAuditListResponse> {
    const response = await axiosClient.get<ApiResource<InternalAuditListResponse>>("/internal-audits", { params });
    return response.data.data;
  },

  async options(): Promise<InternalAuditOptions> {
    const response = await axiosClient.get<ApiResource<InternalAuditOptions>>("/internal-audits/options");
    return response.data.data;
  },

  async show(id: number): Promise<InternalAuditDetail> {
    const response = await axiosClient.get<ApiResource<InternalAuditDetail>>(`/internal-audits/${id}`);
    return response.data.data;
  },

  async create(values: InternalAuditFormValues): Promise<InternalAuditDetail> {
    const response = await axiosClient.post<ApiResource<InternalAuditDetail>>("/internal-audits", values);
    return response.data.data;
  },

  async update(id: number, values: InternalAuditFormValues): Promise<InternalAuditDetail> {
    const response = await axiosClient.put<ApiResource<InternalAuditDetail>>(`/internal-audits/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/internal-audits/${id}`);
  },
};
