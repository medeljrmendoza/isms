import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { DateRangeParams, DrillDownParams, KpiListResponse, KpiSummaryRow } from "../kpi/kpi";
import type { KpiSireOptions } from "./kpiSire";

export const kpiSireService = {
  async options(): Promise<KpiSireOptions> {
    const response = await axiosClient.get<ApiResource<KpiSireOptions>>("/kpi/sire/options");
    return response.data.data;
  },

  async summary(range: DateRangeParams): Promise<KpiSummaryRow[]> {
    const response = await axiosClient.get<ApiResource<KpiSummaryRow[]>>("/kpi/sire/summary", { params: range });
    return response.data.data;
  },

  async reportsByVessel(vesselId: number | string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/sire/reports-by-vessel", {
      params: { vessel_id: vesselId, ...params },
    });
    return response.data.data;
  },
};
