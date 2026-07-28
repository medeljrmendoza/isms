import { axiosClient, sanctumClient } from "../../api/axiosClient";
import type { ApiResource, User } from "./auth";
import type { LoginFormValues } from "./loginSchema";

export const authService = {
  /**
   * Primes the XSRF-TOKEN cookie. Required once before the first
   * state-changing request (login) in a fresh browser session.
   */
  async getCsrfCookie(): Promise<void> {
    await sanctumClient.get("/sanctum/csrf-cookie");
  },

  async login(credentials: LoginFormValues): Promise<User> {
    await authService.getCsrfCookie();
    const response = await axiosClient.post<ApiResource<User>>("/login", credentials);
    return response.data.data;
  },

  async logout(): Promise<void> {
    await axiosClient.post("/logout");
  },

  async getCurrentUser(): Promise<User> {
    const response = await axiosClient.get<ApiResource<User>>("/user");
    return response.data.data;
  },
};
