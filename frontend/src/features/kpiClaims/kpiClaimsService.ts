import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { DateRangeParams, DrillDownParams, KpiListResponse, KpiSummaryRow } from "../kpi/kpi";
import type { KpiClaimsFilter, KpiClaimsOptions } from "./kpiClaims";

export const kpiClaimsService = {
  async options(): Promise<KpiClaimsOptions> {
    const response = await axiosClient.get<ApiResource<KpiClaimsOptions>>("/kpi/claims/options");
    return response.data.data;
  },

  async summary(filter: KpiClaimsFilter, range: DateRangeParams): Promise<KpiSummaryRow[]> {
    const response = await axiosClient.get<ApiResource<KpiSummaryRow[]>>("/kpi/claims/summary", {
      params: { filter, ...range },
    });
    return response.data.data;
  },

  async byVessel(vesselId: number | string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/claims/by-vessel", {
      params: { vessel_id: vesselId, ...params },
    });
    return response.data.data;
  },

  async byCategory(category: string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/claims/by-category", {
      params: { category, ...params },
    });
    return response.data.data;
  },
};
