import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { DefectDetail, DefectListResponse, DefectOptions } from "./defects";
import type { DefectFormValues } from "./defectsSchema";

export interface DefectListParams {
  vessel_id?: number;
  date_from?: string;
  date_to?: string;
  priority?: string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export const defectsService = {
  async options(): Promise<DefectOptions> {
    const response = await axiosClient.get<ApiResource<DefectOptions>>("/defects/options");
    return response.data.data;
  },

  async list(params: DefectListParams): Promise<DefectListResponse> {
    const response = await axiosClient.get<ApiResource<DefectListResponse>>("/defects", { params });
    return response.data.data;
  },

  async show(id: number): Promise<DefectDetail> {
    const response = await axiosClient.get<ApiResource<DefectDetail>>(`/defects/${id}`);
    return response.data.data;
  },

  async create(values: DefectFormValues): Promise<DefectDetail> {
    const response = await axiosClient.post<ApiResource<DefectDetail>>("/defects", values);
    return response.data.data;
  },

  async update(id: number, values: DefectFormValues): Promise<DefectDetail> {
    const response = await axiosClient.put<ApiResource<DefectDetail>>(`/defects/${id}`, values);
    return response.data.data;
  },
};
