import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  VesselDocumentationDetail,
  VesselDocumentationListResponse,
  VesselDocumentationOption,
  VesselDocumentationOptions,
} from "./vesselDocumentation";
import type { VesselDocumentationFormValues } from "./vesselDocumentationSchema";

export interface VesselDocumentationListParams {
  vessel_id: number | string;
  type_id?: number | string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

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

  async documentOptions(vesselId: number | string): Promise<VesselDocumentationOption[]> {
    const response = await axiosClient.get<ApiResource<VesselDocumentationOption[]>>("/vessel-documentation/document-options", {
      params: { vessel_id: vesselId },
    });
    return response.data.data;
  },

  async list(params: VesselDocumentationListParams): Promise<VesselDocumentationListResponse> {
    const response = await axiosClient.get<ApiResource<VesselDocumentationListResponse>>("/vessel-documentation", { params });
    return response.data.data;
  },

  async show(id: number | string): Promise<VesselDocumentationDetail> {
    const response = await axiosClient.get<ApiResource<VesselDocumentationDetail>>(`/vessel-documentation/${id}`);
    return response.data.data;
  },

  async create(values: VesselDocumentationFormValues): Promise<VesselDocumentationDetail> {
    const response = await axiosClient.post<ApiResource<VesselDocumentationDetail>>("/vessel-documentation", values);
    return response.data.data;
  },

  async update(id: number, values: VesselDocumentationFormValues): Promise<VesselDocumentationDetail> {
    const response = await axiosClient.put<ApiResource<VesselDocumentationDetail>>(`/vessel-documentation/${id}`, values);
    return response.data.data;
  },

  async toggleStatus(id: number): Promise<VesselDocumentationDetail> {
    const response = await axiosClient.post<ApiResource<VesselDocumentationDetail>>(`/vessel-documentation/${id}/toggle-status`);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/vessel-documentation/${id}`);
  },
};
