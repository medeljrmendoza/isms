import type { KpiOption } from "../kpi/kpi";

export type KpiPscFilter = "vessel" | "mou" | "nonconformities";

export interface KpiPscOptions {
  vessels: KpiOption[];
  mous: KpiOption[];
}
