import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  ExposureHoursOptions,
  ExposureHoursRecordDetail,
  ExposureHoursRecordListResponse,
  ExposureHoursSummaryResponse,
} from "./exposureHours";
import type { ExposureHoursRecordFormValues } from "./exposureHoursRecordSchema";

export interface DateRangeParams {
  date_from?: string;
  date_to?: string;
}

export interface ExposureHoursRecordListParams extends DateRangeParams {
  vessel_id: number;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

export const exposureHoursService = {
  async options(): Promise<ExposureHoursOptions> {
    const response = await axiosClient.get<ApiResource<ExposureHoursOptions>>("/exposure-hours/options");
    return response.data.data;
  },

  async summary(vesselId: string, range: DateRangeParams): Promise<ExposureHoursSummaryResponse> {
    const response = await axiosClient.get<ApiResource<ExposureHoursSummaryResponse>>("/exposure-hours/summary", {
      params: { vessel_id: vesselId, ...range },
    });
    return response.data.data;
  },

  async list(params: ExposureHoursRecordListParams): Promise<ExposureHoursRecordListResponse> {
    const response = await axiosClient.get<ApiResource<ExposureHoursRecordListResponse>>("/exposure-hours-records", { params });
    return response.data.data;
  },

  async show(id: number): Promise<ExposureHoursRecordDetail> {
    const response = await axiosClient.get<ApiResource<ExposureHoursRecordDetail>>(`/exposure-hours-records/${id}`);
    return response.data.data;
  },

  async create(values: ExposureHoursRecordFormValues): Promise<ExposureHoursRecordDetail> {
    const response = await axiosClient.post<ApiResource<ExposureHoursRecordDetail>>("/exposure-hours-records", values);
    return response.data.data;
  },

  async update(id: number, values: ExposureHoursRecordFormValues): Promise<ExposureHoursRecordDetail> {
    const response = await axiosClient.put<ApiResource<ExposureHoursRecordDetail>>(`/exposure-hours-records/${id}`, values);
    return response.data.data;
  },

  async destroy(id: number): Promise<void> {
    await axiosClient.delete(`/exposure-hours-records/${id}`);
  },
};
