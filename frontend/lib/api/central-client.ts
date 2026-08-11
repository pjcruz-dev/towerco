import axios from "axios";

import { usePlatformAuthStore } from "@/stores/platform-auth-store";

const DEFAULT_TIMEOUT_MS = 20_000;

function parseTimeoutMs(raw: string | undefined, fallback: number): number {
  const parsed = Number(raw?.trim());
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

/** Tenant create/env provisioning runs DB create + many migrations — allow several minutes locally. */
export const PLATFORM_PROVISIONING_TIMEOUT_MS = parseTimeoutMs(
  process.env.NEXT_PUBLIC_PLATFORM_PROVISIONING_TIMEOUT_MS,
  180_000,
);

export const centralApiClient = axios.create({
  baseURL:
    process.env.NEXT_PUBLIC_CENTRAL_API_BASE_URL ??
    process.env.NEXT_PUBLIC_API_BASE_URL ??
    "http://localhost:8000/api/v1",
  timeout: DEFAULT_TIMEOUT_MS,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

centralApiClient.interceptors.request.use((config) => {
  const token = usePlatformAuthStore.getState().accessToken;
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

centralApiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && typeof window !== "undefined") {
      usePlatformAuthStore.getState().clearSession();
      const path = window.location.pathname;
      if (!path.startsWith("/platform/login")) {
        window.location.href = "/platform/login";
      }
    }
    return Promise.reject(error);
  },
);
