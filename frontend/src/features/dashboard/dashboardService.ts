import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { DashboardData } from "./dashboard";

export const dashboardService = {
  async getDashboard(): Promise<DashboardData> {
    const response = await axiosClient.get<ApiResource<DashboardData>>("/dashboard");
    return response.data.data;
  },
};
