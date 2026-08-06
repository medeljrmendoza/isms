import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { PmsActivitiesResponse, PmsActivityDetail, PmsActivityOptions, PmsTicketDetail } from "./pmsActivities";

export interface MarkDoneValues {
  last_done: string;
  unplanned: boolean;
  unplanned_description?: string;
  unplanned_cause?: string;
  remarks?: string;
}

export interface PostponeValues {
  postpone_date: string;
  description: string;
  possible_cause: string;
  remarks?: string;
}

export const pmsActivitiesService = {
  async options(): Promise<PmsActivityOptions> {
    const response = await axiosClient.get<ApiResource<PmsActivityOptions>>("/pms-activities/options");
    return response.data.data;
  },

  async list(params: {
    vessel_id: number | string;
    year?: number;
    department_id?: number | string;
    criticality_id?: number | string;
    main_group_id?: number | string;
    search?: string;
  }): Promise<PmsActivitiesResponse> {
    const response = await axiosClient.get<ApiResource<PmsActivitiesResponse>>("/pms-activities", { params });
    return response.data.data;
  },

  async show(id: number | string): Promise<PmsActivityDetail> {
    const response = await axiosClient.get<ApiResource<PmsActivityDetail>>(`/pms-activities/${id}`);
    return response.data.data;
  },

  async markDone(id: number | string, values: MarkDoneValues): Promise<PmsActivityDetail> {
    const response = await axiosClient.post<ApiResource<PmsActivityDetail>>(`/pms-activities/${id}/mark-done`, values);
    return response.data.data;
  },

  async postpone(id: number | string, values: PostponeValues): Promise<PmsActivityDetail> {
    const response = await axiosClient.post<ApiResource<PmsActivityDetail>>(`/pms-activities/${id}/postpone`, values);
    return response.data.data;
  },

  async ticket(ticketNo: string): Promise<PmsTicketDetail> {
    const response = await axiosClient.get<ApiResource<PmsTicketDetail>>(`/pms-activities/tickets/${encodeURIComponent(ticketNo)}`);
    return response.data.data;
  },
};
