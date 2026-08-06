import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  ExternalAuditDetail,
  ExternalAuditListResponse,
  ExternalAuditOptions,
} from "./externalAudit";

export interface ExternalAuditListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_id?: string;
}

/** Read-only: Add/Edit/Publish/Approve/Delete have no legacy write-back path — see ExternalAuditsPage. */
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
};
