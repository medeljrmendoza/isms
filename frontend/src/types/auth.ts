export interface User {
  id: number;
  name: string;
  username: string;
  email: string;
  force_password_change: boolean;
}

export interface ApiResource<T> {
  data: T;
}

export interface ApiMessage {
  message: string;
}

/** Shape of Laravel's default validation-error response (422/429). */
export interface ApiValidationError {
  message: string;
  errors: Record<string, string[]>;
}

export function isApiValidationError(value: unknown): value is ApiValidationError {
  return (
    typeof value === "object" &&
    value !== null &&
    "errors" in value &&
    typeof (value as { errors: unknown }).errors === "object"
  );
}
