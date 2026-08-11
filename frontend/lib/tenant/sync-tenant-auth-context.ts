import { inferTenantDomainFromEmail } from "@/lib/auth/tenant-session";
import { rememberDevTenantDomain, tenantDomainFromBrowserHostname } from "@/lib/tenant/resolve-tenant-domain";
import type { AuthUser } from "@/types/auth";

const storageKey = "toweros.auth.session";

function resolveTenantIdFromUser(user: AuthUser | null | undefined): string | null {
  if (!user) {
    return null;
  }

  return user.tenantId ?? user.tenantAccesses[0]?.tenantId ?? null;
}

function resolveActiveTenantAccess(user: AuthUser, activeTenantId: string | null) {
  if (!user.tenantAccesses.length) {
    return null;
  }

  if (activeTenantId) {
    return user.tenantAccesses.find((item) => item.tenantId === activeTenantId) ?? user.tenantAccesses[0];
  }

  return user.tenantAccesses[0];
}

/** Domain registered for the signed-in tenant (from API), not a hardcoded hostname. */
export function resolveTenantDomainFromUser(
  user: AuthUser | null | undefined,
  activeTenantId: string | null = null,
): string | null {
  if (!user) {
    return null;
  }

  const access = resolveActiveTenantAccess(user, activeTenantId);

  return (
    access?.tenantDomain?.trim().toLowerCase() ||
    user.tenantDomain?.trim().toLowerCase() ||
    inferTenantDomainFromEmail(user.email)
  );
}

/**
 * Align persisted tenant id/domain with the signed-in user and current browser host.
 * Prevents stale UUIDs in localStorage after db:fresh or tenant re-provision.
 */
export function syncTenantAuthContextFromUser(user: AuthUser | null | undefined): void {
  if (typeof window === "undefined" || !user) {
    return;
  }

  const tenantId = resolveTenantIdFromUser(user);
  const browserDomain = tenantDomainFromBrowserHostname(window.location.hostname);
  const tenantDomain = browserDomain ?? resolveTenantDomainFromUser(user, tenantId);

  if (tenantDomain) {
    rememberDevTenantDomain(tenantDomain);
  }

  const raw = localStorage.getItem(storageKey);
  if (!raw) {
    return;
  }

  try {
    const parsed = JSON.parse(raw) as Record<string, unknown>;
    let changed = false;

    if (tenantId && parsed.activeTenantId !== tenantId) {
      parsed.activeTenantId = tenantId;
      changed = true;
    }

    if (tenantDomain && parsed.tenantDomain !== tenantDomain) {
      parsed.tenantDomain = tenantDomain;
      changed = true;
    }

    if (parsed.user && typeof parsed.user === "object") {
      const current = parsed.user as Record<string, unknown>;
      if (tenantId && current.tenantId !== tenantId) {
        current.tenantId = tenantId;
        changed = true;
      }
      if (tenantDomain && current.tenantDomain !== tenantDomain) {
        current.tenantDomain = tenantDomain;
        changed = true;
      }
    }

    if (changed) {
      localStorage.setItem(storageKey, JSON.stringify(parsed));
    }
  } catch {
    localStorage.removeItem(storageKey);
  }
}

/** Drop cached tenant UUID so API calls use X-Tenant-Domain from the browser host. */
export function clearStaleTenantIdFromSession(): void {
  if (typeof window === "undefined") {
    return;
  }

  const raw = localStorage.getItem(storageKey);
  if (!raw) {
    return;
  }

  try {
    const parsed = JSON.parse(raw) as Record<string, unknown>;
    if (parsed.activeTenantId === null || parsed.activeTenantId === undefined) {
      return;
    }

    parsed.activeTenantId = null;
    if (parsed.user && typeof parsed.user === "object") {
      (parsed.user as Record<string, unknown>).tenantId = null;
    }
    localStorage.setItem(storageKey, JSON.stringify(parsed));
  } catch {
    localStorage.removeItem(storageKey);
  }
}

export function isTenantResolutionApiError(status: number | undefined, message: string): boolean {
  if (status !== 404) {
    return false;
  }

  const normalized = message.trim().toLowerCase();

  return normalized === "tenant not found." || normalized === "tenant domain not found.";
}

export function currentBrowserTenantHostLabel(): string {
  if (typeof window === "undefined") {
    return "your tenant hostname";
  }

  return window.location.hostname || "your tenant hostname";
}
