import { normalizeAuthSession } from "@/modules/identity/auth-normalizer";
import type { AuthSession } from "@/types/auth";
import { previewTenantLoginUrl } from "@/lib/tenant/resolve-tenant-domain";

type HandoffPayload = {
  accessToken: string | null;
  refreshToken: string | null;
  sessionId: string | null;
  user: AuthSession["user"];
  tenantDomain: string;
};

export function buildPlatformImpersonationHandoffUrl(
  tenantDomain: string,
  session: AuthSession,
): string {
  const host = tenantDomain.trim().toLowerCase();
  const payload: HandoffPayload = {
    accessToken: session.accessToken,
    refreshToken: session.refreshToken,
    sessionId: session.sessionId ?? null,
    user: session.user,
    tenantDomain: host,
  };

  const encoded = btoa(JSON.stringify(payload));
  const base = previewTenantLoginUrl(host).replace(/\/login(?:\?.*)?$/, "");

  return `${base}/auth/platform-handoff#${encodeURIComponent(encoded)}`;
}

export function openPlatformImpersonationSession(
  tenantDomain: string,
  session: AuthSession,
): void {
  const url = buildPlatformImpersonationHandoffUrl(tenantDomain, session);
  window.open(url, "_blank", "noopener,noreferrer");
}

export function consumePlatformHandoffPayload(encoded: string): AuthSession | null {
  try {
    const raw = JSON.parse(atob(decodeURIComponent(encoded))) as HandoffPayload;
    if (!raw.accessToken || !raw.refreshToken || !raw.user) {
      return null;
    }

    return normalizeAuthSession({
      access_token: raw.accessToken,
      refresh_token: raw.refreshToken,
      session_id: raw.sessionId,
      mfa_required: false,
      user: raw.user,
    });
  } catch {
    return null;
  }
}
