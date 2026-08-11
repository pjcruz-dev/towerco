import { apiClient } from "@/lib/api/client";

export type TenantBrandingPayload = {
  version: number;
  logo_url: string | null;
  favicon_url: string | null;
  light: Record<string, string>;
  dark: Record<string, string>;
};

export async function fetchTenantBranding(domain: string): Promise<TenantBrandingPayload> {
  const response = await apiClient.get<{ data: TenantBrandingPayload }>("/public/tenant-branding", {
    params: { domain },
  });
  return response.data.data;
}
