import type { KpiOption } from "../kpi/kpi";

export type KpiFlagStateFilter = "vessel" | "nonconformities";

export interface KpiFlagStateOptions {
  vessels: KpiOption[];
}
