import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  CompanyDocumentationListResponse,
  CompanyDocumentationTypeOptions,
} from "./companyDocumentation";

export interface CompanyDocumentationListParams {
  type_id?: number | string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

/** Read-only: Add/Edit/Toggle Status/Delete have no legacy write-back path — see CompanyDocumentationPage. */
export const companyDocumentationService = {
  async typeOptions(): Promise<CompanyDocumentationTypeOptions> {
    const response = await axiosClient.get<ApiResource<CompanyDocumentationTypeOptions>>("/company-documentation/type-options");
    return response.data.data;
  },

  async list(params: CompanyDocumentationListParams): Promise<CompanyDocumentationListResponse> {
    const response = await axiosClient.get<ApiResource<CompanyDocumentationListResponse>>("/company-documentation", { params });
    return response.data.data;
  },
};
