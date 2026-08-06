export interface PmsClassificationOption {
  id: number | string;
  label: string;
}

export interface PmsClassificationOptions {
  departments: PmsClassificationOption[];
  vessel_types: PmsClassificationOption[];
  can_create_record: boolean;
}

export interface PmsClassificationRow {
  id: number | string;
  name: string;
  description: string | null;
  is_active: boolean;
  departments: string[] | null;
  vessel_types: string[] | null;
  department_count: number;
  vessel_type_count: number;
  sub_classification_count: number;
  can_edit: boolean;
}

export interface PmsClassificationDetail {
  id: number | string;
  name: string;
  description: string | null;
  is_active: boolean;
  departments: { id: number | string; name: string }[];
  vessel_types: { id: number | string; name: string }[];
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
  id: number | string;
  pms_classification_id: number | string;
  chart_code: string;
  name: string;
  description: string | null;
  is_active: boolean;
  can_edit: boolean;
}

export interface PmsSubClassificationListResponse {
  classification: { id: number | string; name: string };
  rows: PmsSubClassificationRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  can_create_record: boolean;
}
