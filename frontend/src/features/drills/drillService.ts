import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  DrillCalendarResponse,
  DrillCellItem,
  DrillOptions,
  DrillReportDetail,
} from "./drill";

/** Read-only: legacy never lets shore create/edit/delete a drill report — see DrillCalendarPage. */
export const drillService = {
  async options(): Promise<DrillOptions> {
    const response = await axiosClient.get<ApiResource<DrillOptions>>("/drill-lists/options");
    return response.data.data;
  },

  async calendar(vesselId: number | string, year: number): Promise<DrillCalendarResponse> {
    const response = await axiosClient.get<ApiResource<DrillCalendarResponse>>("/drill-lists/calendar", {
      params: { vessel_id: vesselId, year },
    });
    return response.data.data;
  },

  async cell(drillListId: number | string, vesselId: number | string, year: number, month: number): Promise<DrillCellItem[]> {
    const response = await axiosClient.get<ApiResource<DrillCellItem[]>>("/drill-reports", {
      params: { drill_list_id: drillListId, vessel_id: vesselId, year, month },
    });
    return response.data.data;
  },

  async show(id: number | string): Promise<DrillReportDetail> {
    const response = await axiosClient.get<ApiResource<DrillReportDetail>>(`/drill-reports/${id}`);
    return response.data.data;
  },
};
