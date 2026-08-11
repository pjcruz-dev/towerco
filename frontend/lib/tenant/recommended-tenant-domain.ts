export type TenantEnvironment = "local" | "test" | "staging" | "production";

export function isLocalDevPlatformHost(): boolean {
  if (typeof window === "undefined") {
    return true;
  }

  const host = window.location.hostname;
  return host === "localhost" || host.endsWith(".localhost");
}

/**
 * Recommended tenant hostname for an environment (matches platform add-env sheet).
 */
export function recommendedTenantDomain(
  environment: TenantEnvironment,
  slug: string | null | undefined,
  brandDomain: string | null | undefined,
  options?: { useLocalDevHosts?: boolean },
): string {
  const normalizedSlug =
    (slug ?? "tenant").trim().toLowerCase().replace(/[^a-z0-9-]/g, "") || "tenant";
  const brand =
    (brandDomain ?? "toweros.app").trim().toLowerCase().replace(/^https?:\/\//, "") ||
    "toweros.app";
  const useLocalDevHosts = options?.useLocalDevHosts ?? isLocalDevPlatformHost();

  switch (environment) {
    case "local":
      return `${normalizedSlug}.localhost`;
    case "test":
      return useLocalDevHosts
        ? `test.${normalizedSlug}.localhost`
        : `test.${normalizedSlug}.${brand}`;
    case "staging":
      return useLocalDevHosts
        ? `staging.${normalizedSlug}.localhost`
        : `staging.${normalizedSlug}.${brand}`;
    case "production":
      return useLocalDevHosts
        ? `app.${normalizedSlug}.localhost`
        : `app.${normalizedSlug}.${brand}`;
    default:
      return `app.${normalizedSlug}.${brand}`;
  }
}
