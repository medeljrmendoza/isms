import type { ButtonHTMLAttributes } from "react";

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  isLoading?: boolean;
  variant?: "primary" | "secondary" | "info" | "success";
}

export function Button({
  isLoading = false,
  variant = "primary",
  disabled,
  className = "",
  children,
  ...props
}: ButtonProps) {
  const base =
    "inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60";
  const variants = {
    primary: "bg-slate-900 text-white hover:bg-slate-800 focus:ring-2 focus:ring-slate-400",
    secondary: "bg-white text-slate-700 border border-slate-300 hover:bg-slate-50",
    info: "bg-blue-600 text-white hover:bg-blue-700 focus:ring-2 focus:ring-blue-300",
    success: "bg-green-600 text-white hover:bg-green-700 focus:ring-2 focus:ring-green-300",
  };

  return (
    <button
      className={`${base} ${variants[variant]} ${className}`}
      disabled={disabled || isLoading}
      {...props}
    >
      {isLoading && (
        <span
          className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
          aria-hidden="true"
        />
      )}
      {children}
    </button>
  );
}
