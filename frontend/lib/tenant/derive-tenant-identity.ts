/**
 * Derive org slug and brand domain from a tenant hostname (create-tenant / add-env UX).
 * Mirrors {@link TenantDomainSlugService::deriveSlugFromDomain} and local *.localhost patterns.
 */

const ENV_HOST_PREFIXES = new Set(["test", "staging", "app"]);

/**
 * Optional dev default when the host is `{slug}.localhost` with no brand segment
 * (e.g. set `example.com` in `NEXT_PUBLIC_TENANT_LOCALHOST_DEFAULT_BRAND_DOMAIN`).
 */
export function getLocalhostDefaultBrandDomain(): string {
  const value = process.env.NEXT_PUBLIC_TENANT_LOCALHOST_DEFAULT_BRAND_DOMAIN ?? "";
  return value.trim() ? normalizeBrandDomain(value) : "";
}

export function normalizeTenantSlug(value: string): string {
  const slug = value
    .trim()
    .toLowerCase()
    .replace(/[_\s]+/g, "-")
    .replace(/[^a-z0-9-]/g, "")
    .replace(/^-+|-+$/g, "");

  return slug.slice(0, 32);
}

export function normalizeBrandDomain(value: string): string {
  let domain = value.trim().toLowerCase();
  domain = domain.replace(/^https?:\/\//, "");
  return domain.replace(/\/+$/, "");
}

export function parseTenantHostname(raw: string): string {
  let host = raw.trim().toLowerCase();
  if (!host) {
    return "";
  }
  host = host.replace(/^https?:\/\//, "");
  host = host.split("/")[0]?.split(":")[0] ?? "";

  return host;
}

/**
 * Turn hostname brand segment(s) into a brand domain (FQDN).
 * Single label `example` → `example.com`; multi-label values are kept as-is.
 */
export function brandDomainFromHostLabels(brandParts: string[]): string {
  if (brandParts.length === 0) {
    return "";
  }

  const joined = normalizeBrandDomain(brandParts.join("."));
  if (!joined) {
    return "";
  }

  if (joined.includes(".")) {
    return joined;
  }

  const envDefault = getLocalhostDefaultBrandDomain();
  if (envDefault !== "") {
    const envRoot = envDefault.split(".")[0] ?? "";
    if (envRoot === joined || envDefault === joined) {
      return envDefault;
    }
  }

  return `${joined}.com`;
}

export function deriveTenantIdentityFromHost(rawDomain: string): {
  slug: string;
  brandDomain: string;
} {
  const host = parseTenantHostname(rawDomain);
  if (!host) {
    return { slug: "", brandDomain: "" };
  }

  const parts = host.split(".").filter(Boolean);

  if (host === "localhost" || host.endsWith(".localhost")) {
    let index = 0;
    if (parts[0] && ENV_HOST_PREFIXES.has(parts[0])) {
      index = 1;
    }

    const slug = normalizeTenantSlug(parts[index] ?? "");
    const brandParts = parts.slice(index + 1).filter((part) => part !== "localhost");
    let brandDomain = brandDomainFromHostLabels(brandParts);

    if (brandDomain === "") {
      brandDomain = getLocalhostDefaultBrandDomain();
    }

    return { slug, brandDomain };
  }

  let index = 0;
  if (parts[0] && ENV_HOST_PREFIXES.has(parts[0])) {
    index = 1;
  }

  const slug = normalizeTenantSlug(parts[index] ?? "");
  const brandParts = parts.slice(index + 1);
  const brandDomain =
    brandParts.length > 0
      ? brandDomainFromHostLabels(brandParts)
      : normalizeBrandDomain("toweros.app");

  return { slug, brandDomain };
}
