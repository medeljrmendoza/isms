import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  InternalAuditDetail,
  InternalAuditListResponse,
  InternalAuditOptions,
} from "./internalAudit";

export interface InternalAuditListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

/** Read-only: Add/Edit/Delete have no legacy write-back path — see InternalAuditsPage. */
export const internalAuditService = {
  async list(params: InternalAuditListParams): Promise<InternalAuditListResponse> {
    const response = await axiosClient.get<ApiResource<InternalAuditListResponse>>("/internal-audits", { params });
    return response.data.data;
  },

  async options(): Promise<InternalAuditOptions> {
    const response = await axiosClient.get<ApiResource<InternalAuditOptions>>("/internal-audits/options");
    return response.data.data;
  },

  async show(id: number | string): Promise<InternalAuditDetail> {
    const response = await axiosClient.get<ApiResource<InternalAuditDetail>>(`/internal-audits/${id}`);
    return response.data.data;
  },
};
