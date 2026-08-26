"use client";

import { useSyncExternalStore } from "react";

import { formatOrganizationSlug, organizationSlugFromHostname } from "@/lib/tenant/organization-label";
import { useAuthStore } from "@/stores/auth-store";
import { useTenantBrandingStore } from "@/stores/tenant-branding-store";

function subscribeHostname() {
  return () => {};
}

function getHostname(): string {
  return typeof window !== "undefined" ? window.location.hostname.toLowerCase() : "";
}

function getServerHostname(): string {
  return "";
}

export function useOrganizationLabel(fallback = "TowerOS"): string {
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const brandingLabel = useTenantBrandingStore((state) => state.branding?.organization_label);
  const hostname = useSyncExternalStore(subscribeHostname, getHostname, getServerHostname);

  const access =
    user?.tenantAccesses.find((item) => item.tenantId === activeTenantId) ?? user?.tenantAccesses[0];
  const fromAuth = access?.tenantName?.trim();
  if (fromAuth) {
    return fromAuth;
  }

  const fromBranding = brandingLabel?.trim();
  if (fromBranding) {
    return fromBranding;
  }

  const slug = organizationSlugFromHostname(hostname);
  if (slug) {
    return formatOrganizationSlug(slug);
  }

  return fallback;
}
