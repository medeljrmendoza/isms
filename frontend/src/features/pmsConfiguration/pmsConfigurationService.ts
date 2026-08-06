import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { PmsConfigurationListResponse, PmsConfigurationOptions, PmsConfigurationRow } from "./pmsConfiguration";

export interface PmsConfigurationListParams {
  principal_id: number | string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export const pmsConfigurationService = {
  async options(): Promise<PmsConfigurationOptions> {
    const response = await axiosClient.get<ApiResource<PmsConfigurationOptions>>("/pms-configuration/options");
    return response.data.data;
  },

  async list(params: PmsConfigurationListParams): Promise<PmsConfigurationListResponse> {
    const response = await axiosClient.get<ApiResource<PmsConfigurationListResponse>>("/pms-configuration", { params });
    return response.data.data;
  },

  async update(vesselId: number | string, configuration: string): Promise<PmsConfigurationRow> {
    const response = await axiosClient.put<ApiResource<PmsConfigurationRow>>(`/pms-configuration/${vesselId}`, { configuration });
    return response.data.data;
  },
};
