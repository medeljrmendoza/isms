import { axiosClient } from "./axiosClient";
import type { ApiResource } from "../types/auth";
import type { DashboardData } from "../types/dashboard";

export const dashboardService = {
  async getDashboard(): Promise<DashboardData> {
    const response = await axiosClient.get<ApiResource<DashboardData>>("/dashboard");
    return response.data.data;
  },
};
