import { forwardRef, type InputHTMLAttributes } from "react";

interface CheckboxFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
}

export const CheckboxField = forwardRef<HTMLInputElement, CheckboxFieldProps>(
  ({ label, id, className = "", ...props }, ref) => {
    const fieldId = id ?? props.name;

    return (
      <label htmlFor={fieldId} className={`flex items-center gap-2 text-sm text-slate-700 ${className}`}>
        <input
          ref={ref}
          id={fieldId}
          type="checkbox"
          className="h-4 w-4 rounded border-slate-300 text-slate-700 focus:ring-slate-400"
          {...props}
        />
        {label}
      </label>
    );
  },
);

CheckboxField.displayName = "CheckboxField";
