import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  CompanyDocumentationDetail,
  CompanyDocumentationListResponse,
  CompanyDocumentationOption,
  CompanyDocumentationTypeOptions,
} from "./companyDocumentation";
import type { CompanyDocumentationFormValues } from "./companyDocumentationSchema";

export interface CompanyDocumentationListParams {
  type_id?: number | string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export const companyDocumentationService = {
  async typeOptions(): Promise<CompanyDocumentationTypeOptions> {
    const response = await axiosClient.get<ApiResource<CompanyDocumentationTypeOptions>>("/company-documentation/type-options");
    return response.data.data;
  },

  async documentOptions(): Promise<CompanyDocumentationOption[]> {
    const response = await axiosClient.get<ApiResource<CompanyDocumentationOption[]>>("/company-documentation/document-options");
    return response.data.data;
  },

  async list(params: CompanyDocumentationListParams): Promise<CompanyDocumentationListResponse> {
    const response = await axiosClient.get<ApiResource<CompanyDocumentationListResponse>>("/company-documentation", { params });
    return response.data.data;
  },

  async show(id: number | string): Promise<CompanyDocumentationDetail> {
    const response = await axiosClient.get<ApiResource<CompanyDocumentationDetail>>(`/company-documentation/${id}`);
    return response.data.data;
  },

  async create(values: CompanyDocumentationFormValues): Promise<CompanyDocumentationDetail> {
    const response = await axiosClient.post<ApiResource<CompanyDocumentationDetail>>("/company-documentation", values);
    return response.data.data;
  },

  async update(id: number, values: CompanyDocumentationFormValues): Promise<CompanyDocumentationDetail> {
    const response = await axiosClient.put<ApiResource<CompanyDocumentationDetail>>(`/company-documentation/${id}`, values);
    return response.data.data;
  },

  async toggleStatus(id: number): Promise<CompanyDocumentationDetail> {
    const response = await axiosClient.post<ApiResource<CompanyDocumentationDetail>>(`/company-documentation/${id}/toggle-status`);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/company-documentation/${id}`);
  },
};
