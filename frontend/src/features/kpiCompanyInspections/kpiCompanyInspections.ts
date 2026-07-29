import type { KpiOption } from "../kpi/kpi";

export type KpiCompanyInspectionsFilter = "vessel" | "company" | "nc_vessel" | "nc_company";

export interface KpiCompanyInspectionsOptions {
  vessels: KpiOption[];
}
