import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  CompanyInspectionDetail,
  CompanyInspectionListResponse,
  CompanyInspectionOptions,
} from "./companyInspection";
import type { CompanyInspectionFormValues } from "./companyInspectionSchema";

export interface CompanyInspectionListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
  /** A vessel id, "ALL", or the "COMPANY" sentinel (company-wide reports only). */
  vessel_id?: string;
}

export const companyInspectionService = {
  async list(params: CompanyInspectionListParams): Promise<CompanyInspectionListResponse> {
    const response = await axiosClient.get<ApiResource<CompanyInspectionListResponse>>("/company-inspections", { params });
    return response.data.data;
  },

  async options(): Promise<CompanyInspectionOptions> {
    const response = await axiosClient.get<ApiResource<CompanyInspectionOptions>>("/company-inspections/options");
    return response.data.data;
  },

  async show(id: number): Promise<CompanyInspectionDetail> {
    const response = await axiosClient.get<ApiResource<CompanyInspectionDetail>>(`/company-inspections/${id}`);
    return response.data.data;
  },

  async create(values: CompanyInspectionFormValues): Promise<CompanyInspectionDetail> {
    const response = await axiosClient.post<ApiResource<CompanyInspectionDetail>>("/company-inspections", values);
    return response.data.data;
  },

  async update(id: number, values: CompanyInspectionFormValues): Promise<CompanyInspectionDetail> {
    const response = await axiosClient.put<ApiResource<CompanyInspectionDetail>>(`/company-inspections/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/company-inspections/${id}`);
  },
};
