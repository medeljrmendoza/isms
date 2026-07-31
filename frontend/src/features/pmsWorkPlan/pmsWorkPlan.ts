export interface PmsWorkPlanOption {
  id: number;
  label: string;
}

export interface PmsWorkPlanOptions {
  vessels: PmsWorkPlanOption[];
  departments: PmsWorkPlanOption[];
  job_classes: PmsWorkPlanOption[];
  job_types: PmsWorkPlanOption[];
  components: PmsWorkPlanOption[];
}

export interface PmsWorkPlanRow {
  id: number;
  ticket_no: string;
  department: string | null;
  component: string | null;
  part: string | null;
  activity_name: string;
  incharge: string;
  date_of_activity: string;
}

export interface PmsWorkPlanInventoryLine {
  pms_part_id: number;
  part_name: string | null;
  equipment_name: string | null;
  new_qty: number;
  reconditioned_qty: number;
}

export interface PmsWorkPlanDetail {
  id: number;
  ticket_no: string;
  vessel_id: number;
  vessel: string;
  type: "EQUIPMENT" | "LOCATION";
  pms_department_id: number | null;
  department: string | null;
  pms_equipment_id: number | null;
  equipment_name: string | null;
  pms_part_id: number | null;
  part_name: string | null;
  location: string | null;
  sub_location: string | null;
  activity_name: string;
  pms_job_class_id: number | null;
  job_class: string | null;
  pms_job_type_id: number | null;
  job_type: string | null;
  incharge: string;
  assignee: string | null;
  work_procedure: string | null;
  date_of_activity: string;
  description: string | null;
  remarks: string | null;
  inventory: PmsWorkPlanInventoryLine[];
}

export interface PmsWorkPlanListResponse {
  columns: { key: string; label: string; sortable: boolean }[];
  rows: PmsWorkPlanRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface PmsPartSearchResult {
  id: number;
  part_name: string;
  equipment_name: string | null;
  required_qty: number | null;
  unit: string | null;
  new_qty: number;
  reconditioned_qty: number;
}
