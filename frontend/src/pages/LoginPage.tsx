import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import axios from "axios";
import { Navigate, useLocation, useNavigate } from "react-router-dom";
import { loginSchema, type LoginFormValues } from "../schemas/loginSchema";
import { useAuth } from "../context/AuthContext";
import { isApiValidationError } from "../types/auth";
import { TextField } from "../components/ui/TextField";
import { PasswordField } from "../components/ui/PasswordField";
import { Button } from "../components/ui/Button";
import { Alert } from "../components/ui/Alert";
import { BrandMark } from "../components/ui/BrandMark";
import { ArrowRightCircleIcon, QuestionMarkCircleIcon } from "../components/ui/icons";
import { LoginBackdrop } from "../components/login/LoginBackdrop";

const ENV_LABEL = import.meta.env.VITE_ENV_LABEL;
// Static for now — swap for real version-tracking data once that module migrates.
const SYSTEM_VERSION = "6";
const DATABASE_VERSION = "4";

export function LoginPage() {
  const { login, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [formError, setFormError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
  });

  if (isAuthenticated) {
    const redirectTo = (location.state as { from?: Location })?.from?.pathname ?? "/dashboard";
    return <Navigate to={redirectTo} replace />;
  }

  const onSubmit = async (values: LoginFormValues) => {
    setFormError(null);

    try {
      const user = await login(values);

      if (user.force_password_change) {
        navigate("/change-password", { replace: true });
        return;
      }

      const redirectTo = (location.state as { from?: Location })?.from?.pathname ?? "/dashboard";
      navigate(redirectTo, { replace: true });
    } catch (error) {
      if (axios.isAxiosError(error) && isApiValidationError(error.response?.data)) {
        const fieldErrors = error.response.data.errors;
        const message = fieldErrors.username?.[0] ?? fieldErrors.password?.[0];

        if (message) {
          setFormError(message);
        } else {
          setError("root", { message: error.response.data.message });
        }
        return;
      }

      setFormError("Something went wrong. Please try again.");
    }
  };

  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden px-4">
      <LoginBackdrop />

      <span className="absolute left-6 top-6 z-10 text-3xl font-bold uppercase tracking-[0.3em] text-white sm:left-10 sm:top-10">
        Clearview
      </span>

      <div className="relative z-10 w-full max-w-sm rounded-2xl bg-white p-8 shadow-2xl">
        <div className="flex flex-col items-center gap-3">
          <BrandMark className="h-16 w-24" />
          <h1 className="text-center text-lg font-bold text-slate-800">
            Integrated Ship Management System
          </h1>
          {ENV_LABEL && (
            <p className="text-center text-sm font-bold text-red-600">[{ENV_LABEL}]</p>
          )}
        </div>

        {formError && (
          <div className="mt-5">
            <Alert variant="error">{formError}</Alert>
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)} noValidate className="mt-5 flex flex-col gap-4">
          <TextField
            label="Username"
            autoFocus
            autoComplete="username"
            error={errors.username?.message}
            {...register("username")}
          />

          <PasswordField
            label="Password"
            autoComplete="current-password"
            error={errors.password?.message}
            {...register("password")}
          />

          <div className="mt-1 flex items-center gap-4">
            <Button type="submit" variant="secondary" isLoading={isSubmitting} className="gap-1.5">
              {!isSubmitting && <ArrowRightCircleIcon className="h-4 w-4" />}
              {isSubmitting ? "Signing in..." : "Login"}
            </Button>

            <button
              type="button"
              className="flex items-center gap-1 text-sm text-blue-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:no-underline"
              title="Password reset isn't migrated yet"
              disabled
            >
              <QuestionMarkCircleIcon className="h-4 w-4" />
              Forgot Password
            </button>
          </div>
        </form>

        <div className="mt-6 space-y-1 border-t border-slate-100 pt-4">
          <p className="text-xs text-slate-400">
            © 2013-{new Date().getFullYear()} BTSolve Inc. All rights reserved.
          </p>
          <p className="text-xs font-medium text-slate-500">
            System V. {SYSTEM_VERSION} | Database V. {DATABASE_VERSION}
          </p>
        </div>
      </div>
    </div>
  );
}
