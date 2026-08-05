import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { DateRangeParams, DrillDownParams, KpiListResponse, KpiSummaryRow } from "../kpi/kpi";
import type { KpiInternalAuditsFilter, KpiInternalAuditsOptions } from "./kpiInternalAudits";

export const kpiInternalAuditsService = {
  async options(): Promise<KpiInternalAuditsOptions> {
    const response = await axiosClient.get<ApiResource<KpiInternalAuditsOptions>>("/kpi/internal-audits/options");
    return response.data.data;
  },

  async summary(filter: KpiInternalAuditsFilter, range: DateRangeParams): Promise<KpiSummaryRow[]> {
    const response = await axiosClient.get<ApiResource<KpiSummaryRow[]>>("/kpi/internal-audits/summary", {
      params: { filter, ...range },
    });
    return response.data.data;
  },

  async reportsByVessel(vesselId: number | string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/internal-audits/reports-by-vessel", {
      params: { vessel_id: vesselId, ...params },
    });
    return response.data.data;
  },

  async nonConformitiesByVessel(vesselId: number | string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/internal-audits/nonconformities-by-vessel", {
      params: { vessel_id: vesselId, ...params },
    });
    return response.data.data;
  },
};
