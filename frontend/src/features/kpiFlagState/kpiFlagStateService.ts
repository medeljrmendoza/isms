import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { DateRangeParams, DrillDownParams, KpiListResponse, KpiSummaryRow } from "../kpi/kpi";
import type { KpiFlagStateFilter, KpiFlagStateOptions } from "./kpiFlagState";

export const kpiFlagStateService = {
  async options(): Promise<KpiFlagStateOptions> {
    const response = await axiosClient.get<ApiResource<KpiFlagStateOptions>>("/kpi/flag-state/options");
    return response.data.data;
  },

  async summary(filter: KpiFlagStateFilter, range: DateRangeParams): Promise<KpiSummaryRow[]> {
    const response = await axiosClient.get<ApiResource<KpiSummaryRow[]>>("/kpi/flag-state/summary", {
      params: { filter, ...range },
    });
    return response.data.data;
  },

  async reportsByVessel(vesselId: number | string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/flag-state/reports-by-vessel", {
      params: { vessel_id: vesselId, ...params },
    });
    return response.data.data;
  },

  async nonConformitiesByVessel(vesselId: number | string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/flag-state/nonconformities-by-vessel", {
      params: { vessel_id: vesselId, ...params },
    });
    return response.data.data;
  },
};
