import type { ReactNode } from "react";

interface AlertProps {
  variant?: "error" | "info";
  children: ReactNode;
}

export function Alert({ variant = "error", children }: AlertProps) {
  const styles =
    variant === "error"
      ? "bg-red-50 text-red-700 border-red-200"
      : "bg-blue-50 text-blue-700 border-blue-200";

  return (
    <div role="alert" className={`rounded-md border px-3 py-2 text-sm ${styles}`}>
      {children}
    </div>
  );
}
