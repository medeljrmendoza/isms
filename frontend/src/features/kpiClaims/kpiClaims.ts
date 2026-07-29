import type { KpiOption } from "../kpi/kpi";

export type KpiClaimsFilter = "vessel" | "category";

export interface KpiClaimsOptions {
  vessels: KpiOption[];
}
