import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  DrillCalendarResponse,
  DrillCellItem,
  DrillOptions,
  DrillReportDetail,
} from "./drill";
import type { DrillReportFormValues } from "./drillReportSchema";

export const drillService = {
  async options(): Promise<DrillOptions> {
    const response = await axiosClient.get<ApiResource<DrillOptions>>("/drill-lists/options");
    return response.data.data;
  },

  async calendar(vesselId: number, year: number): Promise<DrillCalendarResponse> {
    const response = await axiosClient.get<ApiResource<DrillCalendarResponse>>("/drill-lists/calendar", {
      params: { vessel_id: vesselId, year },
    });
    return response.data.data;
  },

  async cell(drillListId: number, vesselId: number, year: number, month: number): Promise<DrillCellItem[]> {
    const response = await axiosClient.get<ApiResource<DrillCellItem[]>>("/drill-reports", {
      params: { drill_list_id: drillListId, vessel_id: vesselId, year, month },
    });
    return response.data.data;
  },

  async show(id: number): Promise<DrillReportDetail> {
    const response = await axiosClient.get<ApiResource<DrillReportDetail>>(`/drill-reports/${id}`);
    return response.data.data;
  },

  async update(id: number, values: DrillReportFormValues): Promise<DrillReportDetail> {
    const response = await axiosClient.put<ApiResource<DrillReportDetail>>(`/drill-reports/${id}`, values);
    return response.data.data;
  },
};
