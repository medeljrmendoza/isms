import { useEffect, useState } from "react";
import { notificationService } from "./notificationService";
import type { NotificationCounts } from "./notifications";

export function NotificationBar() {
  const [counts, setCounts] = useState<NotificationCounts | null>(null);

  useEffect(() => {
    notificationService.getCounts().then(setCounts).catch(() => setCounts(null));
  }, []);

  if (!counts) return null;

  const segments = [
    { active: counts.expired > 0, color: "bg-red-600" },
    { active: counts.expiring > 0, color: "bg-orange-500" },
    { active: counts.updates > 0, color: "bg-yellow-400" },
  ].filter((segment) => segment.active);

  if (segments.length === 0) return null;

  return (
    <div className="flex h-1.5 w-full">
      {segments.map((segment, index) => (
        <div key={index} className={`h-full ${segment.color}`} style={{ width: `${100 / segments.length}%` }} />
      ))}
    </div>
  );
}
