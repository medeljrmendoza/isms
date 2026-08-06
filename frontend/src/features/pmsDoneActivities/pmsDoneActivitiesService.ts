import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { PmsDoneActivitiesListResponse, PmsDoneActivityOptions } from "./pmsDoneActivities";

export interface PmsDoneActivitiesListParams {
  vessel_id: number | string;
  date_from: string;
  date_to: string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export const pmsDoneActivitiesService = {
  async options(): Promise<PmsDoneActivityOptions> {
    const response = await axiosClient.get<ApiResource<PmsDoneActivityOptions>>("/pms-done-activities/options");
    return response.data.data;
  },

  async list(params: PmsDoneActivitiesListParams): Promise<PmsDoneActivitiesListResponse> {
    const response = await axiosClient.get<ApiResource<PmsDoneActivitiesListResponse>>("/pms-done-activities", { params });
    return response.data.data;
  },
};
