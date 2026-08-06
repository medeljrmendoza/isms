import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { PmsPartSearchResult, PmsWorkPlanDetail, PmsWorkPlanListResponse, PmsWorkPlanOption, PmsWorkPlanOptions } from "./pmsWorkPlan";
import type { PmsWorkPlanFormValues } from "./pmsWorkPlanSchema";

export const pmsWorkPlanService = {
  async options(vesselId?: number | string): Promise<PmsWorkPlanOptions> {
    const response = await axiosClient.get<ApiResource<PmsWorkPlanOptions>>("/pms-work-plan/options", {
      params: { vessel_id: vesselId },
    });
    return response.data.data;
  },

  async parts(equipmentId: number | string): Promise<PmsWorkPlanOption[]> {
    const response = await axiosClient.get<ApiResource<PmsWorkPlanOption[]>>("/pms-work-plan/parts", {
      params: { equipment_id: equipmentId },
    });
    return response.data.data;
  },

  async searchParts(key: string): Promise<PmsPartSearchResult[]> {
    const response = await axiosClient.get<ApiResource<PmsPartSearchResult[]>>("/pms-work-plan/search-parts", { params: { key } });
    return response.data.data;
  },

  async list(params: { vessel_id: number | string; page: number; per_page: number; search?: string; sort?: string; direction?: "asc" | "desc" }): Promise<PmsWorkPlanListResponse> {
    const response = await axiosClient.get<ApiResource<PmsWorkPlanListResponse>>("/pms-work-plan", { params });
    return response.data.data;
  },

  async show(id: number | string): Promise<PmsWorkPlanDetail> {
    const response = await axiosClient.get<ApiResource<PmsWorkPlanDetail>>(`/pms-work-plan/${id}`);
    return response.data.data;
  },

  async create(values: PmsWorkPlanFormValues): Promise<PmsWorkPlanDetail> {
    const response = await axiosClient.post<ApiResource<PmsWorkPlanDetail>>("/pms-work-plan", values);
    return response.data.data;
  },

  async update(id: number | string, values: PmsWorkPlanFormValues): Promise<PmsWorkPlanDetail> {
    const response = await axiosClient.put<ApiResource<PmsWorkPlanDetail>>(`/pms-work-plan/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number | string): Promise<void> {
    await axiosClient.delete(`/pms-work-plan/${id}`);
  },
};
