import type { KpiOption } from "../kpi/kpi";

export type KpiNonSireFilter = "vessel" | "inspection_type";

export interface KpiNonSireOptions {
  vessels: KpiOption[];
}
