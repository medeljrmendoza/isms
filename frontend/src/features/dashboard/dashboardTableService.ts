import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { TableResponse } from "./dashboard";

export interface TableParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export const dashboardTableService = {
  async fetch(endpoint: string, params: TableParams): Promise<TableResponse> {
    const response = await axiosClient.get<ApiResource<TableResponse>>(endpoint, { params });
    return response.data.data;
  },
};
