import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { DateRangeParams, DrillDownParams, KpiListResponse, KpiSummaryRow } from "../kpi/kpi";
import type { KpiCompanyInspectionsFilter, KpiCompanyInspectionsOptions } from "./kpiCompanyInspections";

export const kpiCompanyInspectionsService = {
  async options(): Promise<KpiCompanyInspectionsOptions> {
    const response = await axiosClient.get<ApiResource<KpiCompanyInspectionsOptions>>("/kpi/company-inspections/options");
    return response.data.data;
  },

  async summary(filter: KpiCompanyInspectionsFilter, range: DateRangeParams): Promise<KpiSummaryRow[]> {
    const response = await axiosClient.get<ApiResource<KpiSummaryRow[]>>("/kpi/company-inspections/summary", {
      params: { filter, ...range },
    });
    return response.data.data;
  },

  async reportsByVessel(vesselId: number, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/company-inspections/reports-by-vessel", {
      params: { vessel_id: vesselId, ...params },
    });
    return response.data.data;
  },

  async reportsByCompany(company: string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/company-inspections/reports-by-company", {
      params: { company, ...params },
    });
    return response.data.data;
  },

  async nonConformitiesByVessel(vesselId: number, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/company-inspections/nonconformities-by-vessel", {
      params: { vessel_id: vesselId, ...params },
    });
    return response.data.data;
  },

  async nonConformitiesByCompany(company: string, params: DrillDownParams): Promise<KpiListResponse> {
    const response = await axiosClient.get<ApiResource<KpiListResponse>>("/kpi/company-inspections/nonconformities-by-company", {
      params: { company, ...params },
    });
    return response.data.data;
  },
};
