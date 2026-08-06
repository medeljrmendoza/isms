import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { PmsRunningHoursOptions, PmsRunningHoursPartsResponse, PmsRunningHoursResponse } from "./pmsRunningHours";

export const pmsRunningHoursService = {
  async options(): Promise<PmsRunningHoursOptions> {
    const response = await axiosClient.get<ApiResource<PmsRunningHoursOptions>>("/pms-running-hours/options");
    return response.data.data;
  },

  async list(vesselId: number | string, month?: number, year?: number): Promise<PmsRunningHoursResponse> {
    const response = await axiosClient.get<ApiResource<PmsRunningHoursResponse>>("/pms-running-hours", {
      params: { vessel_id: vesselId, month, year },
    });
    return response.data.data;
  },

  async parts(
    vesselId: number | string,
    equipmentId: number | string,
    month?: number,
    year?: number,
  ): Promise<PmsRunningHoursPartsResponse> {
    const response = await axiosClient.get<ApiResource<PmsRunningHoursPartsResponse>>("/pms-running-hours/parts", {
      params: { vessel_id: vesselId, equipment_id: equipmentId, month, year },
    });
    return response.data.data;
  },
};
