import { apiClient } from "@/lib/api/client";

export type TenantBrandingPayload = {
  version: number;
  logo_url: string | null;
  favicon_url: string | null;
  light: Record<string, string>;
  dark: Record<string, string>;
  /** From tenant slug (e.g. ATC). Present on known hosts so pre-login chrome can brand correctly. */
  organization_label?: string | null;
};

export function resolveBrandingAssetUrl(url: string | null | undefined): string | null {
  const trimmed = url?.trim() ?? "";
  if (trimmed === "") {
    return null;
  }

  if (/^https?:\/\//i.test(trimmed)) {
    return trimmed;
  }

  if (!trimmed.startsWith("/")) {
    return trimmed;
  }

  const apiBase =
    process.env.NEXT_PUBLIC_API_BASE_URL ??
    process.env.NEXT_PUBLIC_CENTRAL_API_BASE_URL ??
    "http://localhost:8000/api/v1";

  try {
    const origin = new URL(
      apiBase,
      typeof window !== "undefined" ? window.location.href : "http://localhost:8000",
    ).origin;
    return `${origin}${trimmed}`;
  } catch {
    return trimmed;
  }
}

export async function fetchTenantBranding(domain: string): Promise<TenantBrandingPayload> {
  const response = await apiClient.get<{ data: TenantBrandingPayload }>("/public/tenant-branding", {
    params: { domain },
  });
  return response.data.data;
}
