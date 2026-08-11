const ENV_PREFIXES = new Set(["app", "test", "staging", "local"]);

/** Resolve organization slug from a workspace hostname (e.g. app.towerone.localhost → towerone). */
export function organizationSlugFromHostname(hostname: string): string | null {
  const host = hostname.trim().toLowerCase();
  if (!host || host === "localhost" || host === "127.0.0.1") {
    return null;
  }

  const parts = host.split(".").filter(Boolean);
  if (parts.length === 0) {
    return null;
  }

  if (parts.length === 2 && parts[1] === "localhost") {
    return parts[0];
  }

  if (parts.length >= 3 && ENV_PREFIXES.has(parts[0])) {
    return parts[1];
  }

  if (!ENV_PREFIXES.has(parts[0])) {
    return parts[0];
  }

  return null;
}

export function formatOrganizationSlug(slug: string): string {
  return slug.trim().toLowerCase();
}
