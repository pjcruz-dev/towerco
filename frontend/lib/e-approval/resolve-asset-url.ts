/**
 * Resolve E-Approval asset URLs (form logos, etc.) for browser display.
 */
export function resolveEApprovalAssetUrl(path: string | null | undefined): string {
  if (!path?.trim()) {
    return "";
  }

  const trimmed = path.trim();
  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    return trimmed;
  }

  const api = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1";
  const origin = api.replace(/\/api\/v1\/?$/, "");

  if (trimmed.startsWith("/api/")) {
    return `${origin}${trimmed}`;
  }

  return `${origin}${trimmed.startsWith("/") ? trimmed : `/${trimmed}`}`;
}

/**
 * Logo endpoint requires Sanctum cookies — append cache-bust when refreshed.
 */
export function resolveEApprovalFormLogoUrl(
  brandLogoUrl: string | null | undefined,
  cacheKey?: string | number,
): string {
  const base = resolveEApprovalAssetUrl(brandLogoUrl);
  if (!base || cacheKey === undefined) {
    return base;
  }

  const separator = base.includes("?") ? "&" : "?";
  return `${base}${separator}v=${encodeURIComponent(String(cacheKey))}`;
}
