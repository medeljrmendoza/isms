import { axiosClient } from "../../api/axiosClient";
import type { ApiResource } from "../auth/auth";
import type { ManualChapterNode, ManualSearchResult, ManualsOptions } from "./manuals";

export const manualsService = {
  async options(): Promise<ManualsOptions> {
    const response = await axiosClient.get<ApiResource<ManualsOptions>>("/manuals/options");
    return response.data.data;
  },

  async tree(smsType: string, vesselId?: number): Promise<ManualChapterNode[]> {
    const response = await axiosClient.get<ApiResource<ManualChapterNode[]>>("/manuals/tree", {
      params: { sms_type: smsType, vessel_id: vesselId },
    });
    return response.data.data;
  },

  async search(term: string, smsType: string, vesselId?: number): Promise<ManualSearchResult[]> {
    const response = await axiosClient.get<ApiResource<ManualSearchResult[]>>("/manuals/search", {
      params: { q: term, sms_type: smsType, vessel_id: vesselId },
    });
    return response.data.data;
  },
};
