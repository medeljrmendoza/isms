import { axiosClient } from "./axiosClient";
import type { ApiResource } from "../types/auth";
import type { NotificationCounts } from "../types/notifications";

export const notificationService = {
  async getCounts(): Promise<NotificationCounts> {
    const response = await axiosClient.get<ApiResource<NotificationCounts>>(
      "/notifications/counts",
    );
    return response.data.data;
  },
};
