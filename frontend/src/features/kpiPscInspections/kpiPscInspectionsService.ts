import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { DateRangeParams, DrillDownParams, KpiListResponse, KpiSummaryRow } from "../kpi/kpi";
import type { KpiPscFilter, KpiPscOptions } from "./kpiPscInspections";

export const kpiPscInspectionsService = {
  async options(): Promise<KpiPscOptions> {
    const response = await axiosClient.get<ApiResource<KpiPscOptions>>("/kpi/psc-inspections/options");
    return response.data.data;
  },

  async summary(filter: KpiPscFilter, range: DateRangeParams): Promise<KpiSummaryRow[]> {
    const response = await axiosClient.get<ApiResource<KpiSummaryRow[]>>("/kpi/psc-inspections/summary", {
      params: { filter, ...range },
    });
    return response.data.data;
  },

  async reportsByVessel(vesselId: number | string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/psc-inspections/reports-by-vessel", {
      params: { vessel_id: vesselId, ...params },
    });
    return response.data.data;
  },

  async reportsByMou(mouId: number | string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/psc-inspections/reports-by-mou", {
      params: { mou_id: mouId, ...params },
    });
    return response.data.data;
  },

  async nonConformitiesByVessel(vesselId: number | string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/psc-inspections/nonconformities-by-vessel", {
      params: { vessel_id: vesselId, ...params },
    });
    return response.data.data;
  },
};
