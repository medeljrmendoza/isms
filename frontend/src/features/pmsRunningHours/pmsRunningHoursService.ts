import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { PmsRunningHoursOptions, PmsRunningHoursResponse } from "./pmsRunningHours";

export const pmsRunningHoursService = {
  async options(): Promise<PmsRunningHoursOptions> {
    const response = await axiosClient.get<ApiResource<PmsRunningHoursOptions>>("/pms-running-hours/options");
    return response.data.data;
  },

  async list(vesselId: number, month?: number, year?: number): Promise<PmsRunningHoursResponse> {
    const response = await axiosClient.get<ApiResource<PmsRunningHoursResponse>>("/pms-running-hours", {
      params: { vessel_id: vesselId, month, year },
    });
    return response.data.data;
  },

  async update(values: { equipment_id: number; date: string; hours: number; remarks?: string }): Promise<void> {
    await axiosClient.post("/pms-running-hours/update", values);
  },

  async proceedNextMonth(vesselId: number): Promise<void> {
    await axiosClient.post("/pms-running-hours/proceed-next-month", { vessel_id: vesselId });
  },
};
