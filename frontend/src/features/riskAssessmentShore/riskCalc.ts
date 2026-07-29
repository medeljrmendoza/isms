/** Ported from add_risk_assessment_v.php's get_risk_shore(): severity * likelihood banding. */
export function computeRisk(severity: number, likelihood: number): string {
  const total = severity * likelihood;
  if (total <= 6) return "LOW";
  if (total >= 8 && total <= 12) return "MID";
  if (total >= 15) return "HIGH";
  return "";
}

export function riskBadgeClass(value: string | null | undefined): string {
  switch (value) {
    case "LOW":
      return "bg-sky-100 text-sky-700";
    case "MID":
      return "bg-amber-100 text-amber-700";
    case "HIGH":
      return "bg-red-100 text-red-700";
    default:
      return "bg-slate-100 text-slate-600";
  }
}
