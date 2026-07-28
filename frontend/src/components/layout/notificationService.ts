import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../../features/auth/auth";
import type { NotificationCounts } from "./notifications";

export const notificationService = {
  async getCounts(): Promise<NotificationCounts> {
    const response = await axiosClient.get<ApiResource<NotificationCounts>>(
      "/notifications/counts",
    );
    return response.data.data;
  },
};
