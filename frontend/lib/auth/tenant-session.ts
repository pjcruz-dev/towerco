import { readDevTenantDomain, resolveTenantDomainForApi } from "@/lib/tenant/resolve-tenant-domain";

type SessionTenantPayload = {
  tenantDomain?: string | null;
  user?: { email?: string | null } | null;
};

export function inferTenantDomainFromEmail(email?: string | null): string | null {
  if (!email || !email.includes("@")) {
    return null;
  }

  return email.split("@")[1]?.trim().toLowerCase() ?? null;
}

export function resolveSessionTenantDomain(session: SessionTenantPayload): string | null {
  const explicit = session.tenantDomain?.trim().toLowerCase();
  if (explicit) {
    return explicit;
  }

  return inferTenantDomainFromEmail(session.user?.email);
}

/**
 * Returns true when a restored session belongs to the tenant host currently open in the browser.
 */
export function sessionMatchesCurrentTenant(session: SessionTenantPayload): boolean {
  const sessionDomain = resolveSessionTenantDomain(session);
  const currentDomain = resolveTenantDomainForApi();

  if (currentDomain) {
    if (sessionDomain === currentDomain) {
      return true;
    }

    const emailDomain = inferTenantDomainFromEmail(session.user?.email);
    return emailDomain === currentDomain;
  }

  if (!sessionDomain) {
    return true;
  }

  const devDomain = readDevTenantDomain();
  return devDomain === sessionDomain;
}

export function resolveSessionTenantDomainForStorage(): string | null {
  return resolveTenantDomainForApi() ?? readDevTenantDomain();
}
