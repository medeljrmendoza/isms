import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { DateRangeParams, DrillDownParams, KpiListResponse, KpiSummaryRow } from "../kpi/kpi";
import type { KpiNonSireFilter, KpiNonSireOptions } from "./kpiNonSire";

export const kpiNonSireService = {
  async options(): Promise<KpiNonSireOptions> {
    const response = await axiosClient.get<ApiResource<KpiNonSireOptions>>("/kpi/non-sire/options");
    return response.data.data;
  },

  async summary(filter: KpiNonSireFilter, range: DateRangeParams): Promise<KpiSummaryRow[]> {
    const response = await axiosClient.get<ApiResource<KpiSummaryRow[]>>("/kpi/non-sire/summary", {
      params: { filter, ...range },
    });
    return response.data.data;
  },

  async reportsByVessel(vesselId: number, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/non-sire/reports-by-vessel", {
      params: { vessel_id: vesselId, ...params },
    });
    return response.data.data;
  },

  async reportsByInspectionType(inspectionType: string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/non-sire/reports-by-inspection-type", {
      params: { inspection_type: inspectionType, ...params },
    });
    return response.data.data;
  },
};
