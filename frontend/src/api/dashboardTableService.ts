import { axiosClient } from "./axiosClient";
import type { ApiResource } from "../types/auth";
import type { TableResponse } from "../types/dashboard";

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
