import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  NonconformityDetail,
  NonconformityListResponse,
  NonconformityOptions,
} from "./nonconformity";

export interface NonconformityListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  vessel_company?: string;
  date_from?: string;
  date_to?: string;
}

/** Read-only: Add/Edit/Publish/Approve/Delete/Reopen have no legacy write-back path — see NonconformitiesPage. */
export const nonconformityService = {
  async list(params: NonconformityListParams): Promise<NonconformityListResponse> {
    const response = await axiosClient.get<ApiResource<NonconformityListResponse>>("/nonconformities", { params });
    return response.data.data;
  },

  async options(): Promise<NonconformityOptions> {
    const response = await axiosClient.get<ApiResource<NonconformityOptions>>("/nonconformities/options");
    return response.data.data;
  },

  async show(id: number | string): Promise<NonconformityDetail> {
    const response = await axiosClient.get<ApiResource<NonconformityDetail>>(`/nonconformities/${id}`);
    return response.data.data;
  },
};
