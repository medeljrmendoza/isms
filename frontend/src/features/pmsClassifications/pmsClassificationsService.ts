import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  PmsClassificationDetail,
  PmsClassificationListResponse,
  PmsClassificationOptions,
  PmsClassificationRow,
  PmsSubClassificationListResponse,
  PmsSubClassificationRow,
} from "./pmsClassifications";

export interface PmsClassificationListParams {
  department_id?: number | string;
  vessel_type_id?: number | string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export interface PmsClassificationFormValues {
  name: string;
  description?: string;
  department_ids: (number | string)[];
  vessel_type_ids: (number | string)[];
}

export interface PmsSubClassificationFormValues {
  chart_code: string;
  name: string;
  description?: string;
}

export const pmsClassificationsService = {
  async options(): Promise<PmsClassificationOptions> {
    const response = await axiosClient.get<ApiResource<PmsClassificationOptions>>("/pms-classifications/options");
    return response.data.data;
  },

  async list(params: PmsClassificationListParams): Promise<PmsClassificationListResponse> {
    const response = await axiosClient.get<ApiResource<PmsClassificationListResponse>>("/pms-classifications", { params });
    return response.data.data;
  },

  async show(id: number | string): Promise<PmsClassificationDetail> {
    const response = await axiosClient.get<ApiResource<PmsClassificationDetail>>(`/pms-classifications/${id}`);
    return response.data.data;
  },

  async create(values: PmsClassificationFormValues): Promise<PmsClassificationDetail> {
    const response = await axiosClient.post<ApiResource<PmsClassificationDetail>>("/pms-classifications", values);
    return response.data.data;
  },

  async update(id: number | string, values: PmsClassificationFormValues): Promise<PmsClassificationDetail> {
    const response = await axiosClient.put<ApiResource<PmsClassificationDetail>>(`/pms-classifications/${id}`, values);
    return response.data.data;
  },

  async toggleStatus(id: number | string): Promise<PmsClassificationRow> {
    const response = await axiosClient.post<ApiResource<PmsClassificationRow>>(`/pms-classifications/${id}/toggle-status`);
    return response.data.data;
  },

  async subList(
    classificationId: number | string,
    params: { page: number; per_page: number; search?: string; sort?: string; direction?: "asc" | "desc" },
  ): Promise<PmsSubClassificationListResponse> {
    const response = await axiosClient.get<ApiResource<PmsSubClassificationListResponse>>(
      `/pms-classifications/${classificationId}/sub-classifications`,
      { params },
    );
    return response.data.data;
  },

  async subCreate(classificationId: number | string, values: PmsSubClassificationFormValues): Promise<PmsSubClassificationRow> {
    const response = await axiosClient.post<ApiResource<PmsSubClassificationRow>>(
      `/pms-classifications/${classificationId}/sub-classifications`,
      values,
    );
    return response.data.data;
  },

  async subUpdate(id: number | string, values: PmsSubClassificationFormValues): Promise<PmsSubClassificationRow> {
    const response = await axiosClient.put<ApiResource<PmsSubClassificationRow>>(`/pms-sub-classifications/${id}`, values);
    return response.data.data;
  },

  async subToggleStatus(id: number | string): Promise<PmsSubClassificationRow> {
    const response = await axiosClient.post<ApiResource<PmsSubClassificationRow>>(`/pms-sub-classifications/${id}/toggle-status`);
    return response.data.data;
  },
};
