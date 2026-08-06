import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type {
  ExposureHoursOptions,
  ExposureHoursRecordDetail,
  ExposureHoursRecordListResponse,
  ExposureHoursSummaryResponse,
} from "./exposureHours";

export interface DateRangeParams {
  date_from?: string;
  date_to?: string;
}

export interface ExposureHoursRecordListParams extends DateRangeParams {
  vessel_id: number | string;
  page: number;
  per_page: number;
  search?: string;
  sort?: string;
  direction?: "asc" | "desc";
}

/** Read-only: Add/Edit/Delete have no legacy write-back path — see ExposureHoursRecordsPage. */
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

  async show(id: number | string): Promise<ExposureHoursRecordDetail> {
    const response = await axiosClient.get<ApiResource<ExposureHoursRecordDetail>>(`/exposure-hours-records/${id}`);
    return response.data.data;
  },
};
