import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { PmsDepartmentListResponse, PmsDepartmentRow } from "./pmsDepartment";

export interface PmsDepartmentListParams {
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export const pmsDepartmentService = {
  async list(params: PmsDepartmentListParams): Promise<PmsDepartmentListResponse> {
    const response = await axiosClient.get<ApiResource<PmsDepartmentListResponse>>("/pms-departments", { params });
    return response.data.data;
  },

  async create(name: string): Promise<PmsDepartmentRow> {
    const response = await axiosClient.post<ApiResource<PmsDepartmentRow>>("/pms-departments", { name });
    return response.data.data;
  },

  async update(id: number, name: string): Promise<PmsDepartmentRow> {
    const response = await axiosClient.put<ApiResource<PmsDepartmentRow>>(`/pms-departments/${id}`, { name });
    return response.data.data;
  },

  async toggleStatus(id: number): Promise<PmsDepartmentRow> {
    const response = await axiosClient.post<ApiResource<PmsDepartmentRow>>(`/pms-departments/${id}/toggle-status`);
    return response.data.data;
  },
};
