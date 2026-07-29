import type { KpiOption } from "../kpi/kpi";

export type KpiInternalAuditsFilter = "vessel" | "nonconformities";

export interface KpiInternalAuditsOptions {
  vessels: KpiOption[];
}
