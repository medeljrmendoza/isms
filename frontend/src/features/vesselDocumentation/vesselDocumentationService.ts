import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  VesselDocumentationListResponse,
  VesselDocumentationOption,
  VesselDocumentationOptions,
} from "./vesselDocumentation";

export interface VesselDocumentationListParams {
  vessel_id: number | string;
  type_id?: number | string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

/** Read-only: Add/Edit/Toggle Status/Delete have no legacy write-back path — see VesselDocumentationPage. */
export const vesselDocumentationService = {
  async options(): Promise<VesselDocumentationOptions> {
    const response = await axiosClient.get<ApiResource<VesselDocumentationOptions>>("/vessel-documentation/options");
    return response.data.data;
  },

  async typeOptions(vesselId: number | string): Promise<VesselDocumentationOption[]> {
    const response = await axiosClient.get<ApiResource<VesselDocumentationOption[]>>("/vessel-documentation/type-options", {
      params: { vessel_id: vesselId },
    });
    return response.data.data;
  },

  async list(params: VesselDocumentationListParams): Promise<VesselDocumentationListResponse> {
    const response = await axiosClient.get<ApiResource<VesselDocumentationListResponse>>("/vessel-documentation", { params });
    return response.data.data;
  },
};
