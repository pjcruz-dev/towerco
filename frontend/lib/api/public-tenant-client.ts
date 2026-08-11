import axios from "axios";

import { tenantDomainFromBrowserHostname } from "@/lib/tenant/resolve-tenant-domain";

function resolvePublicTenantHeaders(): Record<string, string> {
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
  };

  if (typeof window === "undefined") {
    return headers;
  }

  const browserDomain = tenantDomainFromBrowserHostname(window.location.hostname);
  if (browserDomain) {
    headers["X-Tenant-Domain"] = browserDomain;
    return headers;
  }

  const fromEnv = process.env.NEXT_PUBLIC_DEFAULT_TENANT_DOMAIN?.trim().toLowerCase();
  if (fromEnv) {
    headers["X-Tenant-Domain"] = fromEnv;
  }

  return headers;
}

export const publicTenantApiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1",
  timeout: 30000,
});

publicTenantApiClient.interceptors.request.use((config) => {
  const tenantHeaders = resolvePublicTenantHeaders();
  const isFormData = typeof FormData !== "undefined" && config.data instanceof FormData;

  if (isFormData) {
    delete tenantHeaders["Content-Type"];
  }

  const headers = config.headers;
  if (headers && typeof headers === "object") {
    if (isFormData) {
      // Let the browser set multipart boundary — a bare multipart Content-Type breaks uploads.
      if (typeof (headers as { delete?: (key: string) => void }).delete === "function") {
        (headers as { delete: (key: string) => void }).delete("Content-Type");
      } else {
        delete (headers as Record<string, unknown>)["Content-Type"];
        delete (headers as Record<string, unknown>)["content-type"];
      }
    }

    if (typeof (headers as { set?: (key: string, value: string) => void }).set === "function") {
      for (const [key, value] of Object.entries(tenantHeaders)) {
        (headers as { set: (key: string, value: string) => void }).set(key, value);
      }
    } else {
      Object.assign(headers, tenantHeaders);
    }
  }

  return config;
});
