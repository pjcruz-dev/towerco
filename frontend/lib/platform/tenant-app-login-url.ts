import { tenantLoginUrl as buildTenantLoginUrl } from "@/lib/tenant/resolve-tenant-domain";

/**
 * Builds the tenant web app login URL for the primary host (first domain).
 * Override with NEXT_PUBLIC_TENANT_LOGIN_URL_TEMPLATE (use {host} or {domain} for the tenant host).
 */
export function tenantAppLoginUrl(primaryDomain: string | undefined): string | null {
  if (!primaryDomain?.trim()) {
    return null;
  }

  return buildTenantLoginUrl(primaryDomain.trim());
}
