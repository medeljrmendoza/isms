import { Link, useLocation } from "react-router-dom";

function titleFromPath(pathname: string): string {
  const segment = pathname.split("/").filter(Boolean)[0] ?? "";
  return segment
    .split(/[_-]/)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ") || "This page";
}

export function ComingSoonPage() {
  const location = useLocation();

  return (
    <div className="flex flex-col items-center justify-center gap-3 py-24 text-center">
      <h1 className="text-lg font-semibold text-slate-800">{titleFromPath(location.pathname)}</h1>
      <p className="max-w-sm text-sm text-slate-500">
        This module hasn't been migrated to the new system yet.
      </p>
      <Link to="/dashboard" className="text-sm text-blue-600 hover:underline">
        Back to Dashboard
      </Link>
    </div>
  );
}
