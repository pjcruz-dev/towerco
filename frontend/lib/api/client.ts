import axios from "axios";
import type { AxiosError, AxiosRequestConfig } from "axios";

import { readDevTenantDomain, tenantDomainFromBrowserHostname } from "@/lib/tenant/resolve-tenant-domain";
import {
  clearStaleTenantIdFromSession,
  isTenantResolutionApiError,
  resolveTenantDomainFromUser,
} from "@/lib/tenant/sync-tenant-auth-context";
import { normalizeAuthSession } from "@/modules/identity/auth-normalizer";
import {
  resolveAuthAccessToken,
  resolveAuthRefreshToken,
  resolveAuthSessionId,
  useAuthStore,
} from "@/stores/auth-store";
import { useSubscriptionAccessStore } from "@/stores/subscription-access-store";

function resolveTenantIdHeader(): string | null {
  const state = useAuthStore.getState();
  const fromSession =
    state.activeTenantId ??
    state.user?.tenantId ??
    state.user?.tenantAccesses[0]?.tenantId ??
    state.pendingMfa?.user?.tenantId ??
    state.pendingMfa?.user?.tenantAccesses[0]?.tenantId ??
    null;
  const fromEnv = process.env.NEXT_PUBLIC_DEFAULT_TENANT_ID?.trim() || null;

  return fromSession ?? fromEnv;
}

/**
 * Tenant API headers for central API hosts. Resolution order:
 * 1) Browser hostname (any provisioned tenant domain)
 * 2) Domain from /me (tenant_domain) or dev session
 * 3) Tenant UUID only when no domain is known (platform multi-tenant picker)
 */
function applyTenantContextHeaders(headers: Record<string, string>): void {
  if (typeof window === "undefined" || isApiSameHostnameAsPage()) {
    return;
  }

  const state = useAuthStore.getState();
  const browserDomain = tenantDomainFromBrowserHostname(window.location.hostname);
  if (browserDomain) {
    headers["X-Tenant-Domain"] = browserDomain;
    return;
  }

  const fromUser = resolveTenantDomainFromUser(state.user, state.activeTenantId);
  const fromSession =
    state.tenantDomain?.trim().toLowerCase() || readDevTenantDomain() || fromUser || null;

  if (fromSession) {
    headers["X-Tenant-Domain"] = fromSession;
    return;
  }

  const tenantId = resolveTenantIdHeader();
  if (tenantId) {
    headers["X-Tenant-Id"] = tenantId;
  }
}

/**
 * When the SPA and API share the same hostname (e.g. `acme.example.com` with `/api` routed to Laravel),
 * tenancy must come from the request Host — do not send `X-Tenant-Domain` from the browser.
 */
function isApiSameHostnameAsPage(): boolean {
  if (typeof window === "undefined") {
    return false;
  }
  try {
    const apiBase = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1";
    const apiHostname = new URL(apiBase, window.location.href).hostname.toLowerCase();
    return apiHostname === window.location.hostname.toLowerCase();
  } catch {
    return false;
  }
}

function resolveApiTimeoutMs(): number {
  const fromEnv = Number(process.env.NEXT_PUBLIC_API_TIMEOUT_MS);
  if (Number.isFinite(fromEnv) && fromEnv > 0) {
    return fromEnv;
  }

  if (process.env.NEXT_PUBLIC_APP_ENV === "local") {
    return 90_000;
  }

  return 20_000;
}

export const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1",
  timeout: resolveApiTimeoutMs(),
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

let refreshPromise: Promise<void> | null = null;

async function refreshAccessToken() {
  if (!refreshPromise) {
    refreshPromise = (async () => {
      const state = useAuthStore.getState();
      const refreshToken = resolveAuthRefreshToken();
      if (!refreshToken) {
        state.clearSession();
        return;
      }

      try {
        const headers: Record<string, string> = {
          "Content-Type": "application/json",
          Accept: "application/json",
        };
        applyTenantContextHeaders(headers);

        const response = await axios.post<{ data: unknown }>(
          `${
            process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1"
          }${process.env.NEXT_PUBLIC_AUTH_REFRESH_PATH ?? "/auth/refresh"}`,
          { refresh_token: refreshToken },
          { headers },
        );
        const nextSession = normalizeAuthSession(response.data.data);
        useAuthStore.getState().setSession(nextSession);
      } catch {
        useAuthStore.getState().clearSession();
      } finally {
        refreshPromise = null;
      }
    })();
  }

  await refreshPromise;
}

apiClient.interceptors.request.use((config) => {
  const token = resolveAuthAccessToken();

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  const headers = config.headers as Record<string, string>;
  applyTenantContextHeaders(headers);

  const sessionId = resolveAuthSessionId();
  if (sessionId) {
    config.headers["X-Session-Id"] = sessionId;
  }

  if (config.data instanceof FormData) {
    delete config.headers["Content-Type"];
  }

  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const status = error.response?.status;
    const config = (error.config ?? {}) as AxiosRequestConfig & {
      _retry?: boolean;
      _tenantDomainRetry?: boolean;
    };

    const errorData =
      typeof error.response?.data === "object" && error.response?.data !== null
        ? (error.response.data as { message?: string; code?: string })
        : null;
    const apiMessage = String(errorData?.message ?? "");
    const apiCode = errorData?.code ?? "";

    if (apiCode === "subscription_read_only") {
      useSubscriptionAccessStore.getState().setAccessNotice("read_only", apiMessage);
    } else if (apiCode === "subscription_suspended" || status === 402) {
      useSubscriptionAccessStore.getState().setAccessNotice("blocked", apiMessage);
    }

    if (
      isTenantResolutionApiError(status, apiMessage) &&
      !config._tenantDomainRetry &&
      typeof window !== "undefined"
    ) {
      const browserDomain = tenantDomainFromBrowserHostname(window.location.hostname);
      if (browserDomain) {
        config._tenantDomainRetry = true;
        clearStaleTenantIdFromSession();
        useAuthStore.getState().syncTenantContext();

        config.headers = config.headers ?? {};
        const headers = config.headers as Record<string, string>;
        delete headers["X-Tenant-Id"];
        headers["X-Tenant-Domain"] = browserDomain;

        return apiClient(config);
      }
    }

    if (status === 401 && !config._retry) {
      config._retry = true;
      await refreshAccessToken();

      const nextToken = resolveAuthAccessToken();
      if (nextToken) {
        config.headers = config.headers ?? {};
        config.headers.Authorization = `Bearer ${nextToken}`;
        return apiClient(config);
      }
    }

    if (status === 403 && useAuthStore.getState().pendingMfa) {
      return Promise.reject(error);
    }

    if (isTenantResolutionApiError(status, apiMessage) && typeof window !== "undefined") {
      useAuthStore.getState().clearSession();
      const loginPath = "/login";
      if (!window.location.pathname.startsWith(loginPath)) {
        const host = window.location.hostname || "your-tenant-host";
        window.location.assign(
          `${loginPath}?reason=tenant_missing&host=${encodeURIComponent(host)}`,
        );
      }
    }

    return Promise.reject(error);
  },
);
