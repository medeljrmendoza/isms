export interface PmsClassificationOption {
  id: number;
  label: string;
}

export interface PmsClassificationOptions {
  departments: PmsClassificationOption[];
  vessel_types: PmsClassificationOption[];
}

export interface PmsClassificationRow {
  id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  departments: string[] | null;
  vessel_types: string[] | null;
  department_count: number;
  vessel_type_count: number;
  sub_classification_count: number;
}

export interface PmsClassificationDetail {
  id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  departments: { id: number; name: string }[];
  vessel_types: { id: number; name: string }[];
}

export interface PmsClassificationListResponse {
  rows: PmsClassificationRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface PmsSubClassificationRow {
  id: number;
  pms_classification_id: number;
  chart_code: string;
  name: string;
  description: string | null;
  is_active: boolean;
}

export interface PmsSubClassificationListResponse {
  classification: { id: number; name: string };
  rows: PmsSubClassificationRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
