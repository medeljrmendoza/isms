import axios from "axios";

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000";

/**
 * Talks to the Laravel API using Sanctum's cookie-based SPA auth — no
 * bearer token is ever stored client-side. `withCredentials` sends the
 * session cookie and lets Laravel set/read the XSRF-TOKEN cookie that
 * Axios automatically echoes back as the X-XSRF-TOKEN header.
 */
export const axiosClient = axios.create({
  baseURL: `${API_BASE_URL}/api`,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: "application/json",
  },
});

/** Separate instance for the one non-/api route Sanctum needs. */
export const sanctumClient = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true,
  withXSRFToken: true,
});
