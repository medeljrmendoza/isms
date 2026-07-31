export interface DefectOption {
  id: number;
  label: string;
}

export interface DefectOptions {
  vessels: DefectOption[];
}

export interface DefectRow {
  id: number;
  sl_no: string;
  vessel: string;
  defect_date: string;
  priority: string | null;
  category: string | null;
  compl_code: string;
  description: string;
  present_status: string | null;
  expected_compl_date: string | null;
  compl_date: string | null;
}

export interface DefectDetail extends DefectRow {
  vessel_id: number;
  raised_by: string | null;
  vessel_remarks: string | null;
  shore_remarks: string | null;
}

export interface DefectListResponse {
  rows: DefectRow[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export const PRIORITY_LABELS: Record<string, string> = {
  "1": "1 - Critical/Environment Critical / Safety Equipment",
  "2": "2 - Mooring/Cargo/Boiler/Purifiers/Non-critical Equipment",
  "3": "3 - Rest of Equipment/Fittings other than the above",
};

export const CATEGORY_LABELS: Record<string, string> = {
  N: "N - NCR",
  T: "T - Trouble Report",
  O: "O - Observation",
};

export const RAISED_BY_LABELS: Record<string, string> = {
  VSL: "VSL - Vessel reported Defects",
  VIR: "VIR - Vessel inspection by Office",
  IAR: "IAR - Internal Audit by Office",
  INC: "INC - Incident related Defects",
  TPR: "TPR - Vessel inspection by PSC / Oil Major",
};

export const COMPL_CODE_LABELS: Record<string, string> = {
  P: "P - Pending",
  I: "I - In progress",
  C: "C - Completed",
  H: "H - Hot Work by Shipstaff",
  D: "D - Shore Assistance / DD",
};
