export interface PmsConfigurationOption {
  id: number | string;
  label: string;
}

export interface PmsConfigurationOptions {
  principals: PmsConfigurationOption[];
}

export interface PmsConfigurationRow {
  id: number | string;
  vessel_name: string;
  short_name: string | null;
  configuration: string | null;
  can_edit: boolean;
}

export interface PmsConfigurationListResponse {
  rows: PmsConfigurationRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export const CONFIGURATION_VALUES = ["SHORE", "VESSEL"] as const;
