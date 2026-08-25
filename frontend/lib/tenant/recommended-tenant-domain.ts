export type TenantEnvironment = "local" | "test" | "staging" | "production";

export function isLocalDevPlatformHost(): boolean {
  if (typeof window === "undefined") {
    return true;
  }

  const host = window.location.hostname;
  return host === "localhost" || host.endsWith(".localhost");
}

function looksLikePublicBrandDomain(brandDomain: string | null | undefined): boolean {
  const brand = (brandDomain ?? "").trim().toLowerCase().replace(/^https?:\/\//, "");
  if (!brand || !brand.includes(".")) {
    return false;
  }
  return !brand.endsWith(".localhost");
}

/**
 * Recommended tenant hostname for an environment (matches platform add-env sheet).
 *
 * Localhost keeps `{slug}` so multiple orgs can coexist on one laptop.
 * Deployed / brand DNS omits slug: `staging.alliancetowers.com`, `app.alliancetowers.com`.
 * Slug is still required as org identity for linking environments (switch env).
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
  let useLocalDevHosts = options?.useLocalDevHosts ?? isLocalDevPlatformHost();
  if (useLocalDevHosts && environment !== "local" && looksLikePublicBrandDomain(brandDomain)) {
    useLocalDevHosts = false;
  }

  switch (environment) {
    case "local":
      return useLocalDevHosts ? `${normalizedSlug}.localhost` : `local.${brand}`;
    case "test":
      return useLocalDevHosts ? `test.${normalizedSlug}.localhost` : `test.${brand}`;
    case "staging":
      return useLocalDevHosts ? `staging.${normalizedSlug}.localhost` : `staging.${brand}`;
    case "production":
      return useLocalDevHosts ? `app.${normalizedSlug}.localhost` : `app.${brand}`;
    default:
      return useLocalDevHosts ? `app.${normalizedSlug}.localhost` : `app.${brand}`;
  }
}
