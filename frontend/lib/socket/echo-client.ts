"use client";

import Echo from "laravel-echo";
import Pusher from "pusher-js";

import { resolveTenantDomainForApi } from "@/lib/tenant/resolve-tenant-domain";

let echoInstance: Echo<"pusher"> | null = null;

/** Realtime is optional in local dev — enable only when Soketi is running. */
export function isEchoEnabled(): boolean {
  return process.env.NEXT_PUBLIC_SOCKET_ENABLED === "true";
}

function resolveApiOrigin(): string {
  const base = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1";
  return base.replace(/\/api\/v1\/?$/, "");
}

function buildAuthHeaders(token: string): Record<string, string> {
  const headers: Record<string, string> = {
    Authorization: `Bearer ${token}`,
    Accept: "application/json",
  };

    if (typeof window !== "undefined") {
      const tenantDomain = resolveTenantDomainForApi();
      if (tenantDomain) {
        headers["X-Tenant-Domain"] = tenantDomain;
      }
    }

  return headers;
}

export function getEcho(token: string): Echo<"pusher"> {
  if (typeof window !== "undefined") {
    (window as Window & { Pusher?: typeof Pusher }).Pusher = Pusher;
  }

  if (echoInstance) {
    return echoInstance;
  }

  const key = process.env.NEXT_PUBLIC_PUSHER_APP_KEY ?? "toweros-local";
  const host = process.env.NEXT_PUBLIC_PUSHER_HOST ?? "127.0.0.1";
  const port = Number(process.env.NEXT_PUBLIC_PUSHER_PORT ?? "6001");
  const scheme = process.env.NEXT_PUBLIC_PUSHER_SCHEME ?? "http";
  const useTls = scheme === "https";

  echoInstance = new Echo({
    broadcaster: "pusher",
    key,
    cluster: process.env.NEXT_PUBLIC_PUSHER_APP_CLUSTER ?? "mt1",
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: useTls,
    encrypted: useTls,
    disableStats: true,
    enabledTransports: ["ws", "wss"],
    authEndpoint: `${resolveApiOrigin()}/api/v1/broadcasting/auth`,
    auth: {
      headers: buildAuthHeaders(token),
    },
  });

  return echoInstance;
}

export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
}
