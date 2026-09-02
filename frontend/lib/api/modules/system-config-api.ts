import { apiClient } from "@/lib/api/client";

export type SystemBrandConfig = {
  application_name: string;
  company_name: string;
  show_company_beside_logo: boolean;
  logo_background: string;
  application_name_color: string;
};

export type SystemThemeConfig = {
  sidebar_dark: string;
  accent: string;
  base_light: string;
  shell_layout: "sidebar" | "navbar";
  lock_layout: boolean;
  preset: string | null;
};

export type SystemLocalizationConfig = {
  ai_business_context: string;
  country: string;
  office_city: string;
  currency_symbol: string;
  currency_code: string;
  timezone: string;
  locale: string;
  date_format: string;
  locations_entity: string;
};

export type SystemSupportConfig = {
  support_email: string;
  contact_phone: string;
  office_address: string;
  website_url: string;
  copyright_footer: string;
};

export type SystemSecurityConfig = {
  login_background_url: string | null;
  gps_tracking_enabled: boolean;
};

export type SystemIntegrationsConfig = {
  maps_provider: string;
  maps_note: string;
};

export type SystemConfigPayload = {
  brand: SystemBrandConfig;
  theme: SystemThemeConfig;
  localization: SystemLocalizationConfig;
  support: SystemSupportConfig;
  security: SystemSecurityConfig;
  integrations: SystemIntegrationsConfig;
};

export type SystemConfigResponse = {
  config: SystemConfigPayload;
  branding: {
    logo_url: string | null;
    favicon_url: string | null;
    company_address: string | null;
    company_phone: string | null;
    company_email: string | null;
    company_tin: string | null;
    light: Record<string, string>;
    dark: Record<string, string>;
  };
  tenant_slug: string | null;
};

export async function fetchSystemConfig(): Promise<SystemConfigResponse> {
  const response = await apiClient.get<{ data: SystemConfigResponse }>("/admin/system");
  return response.data.data;
}

export async function updateSystemConfig(
  payload: Partial<SystemConfigPayload>,
): Promise<SystemConfigResponse> {
  const response = await apiClient.patch<{ data: SystemConfigResponse }>("/admin/system", payload);
  return response.data.data;
}

export async function uploadSystemBrandingAsset(
  asset: "logo" | "favicon",
  file: File,
): Promise<SystemConfigResponse> {
  const form = new FormData();
  form.append("file", file);
  const response = await apiClient.post<{ data: SystemConfigResponse }>(
    `/admin/system/branding/${asset}`,
    form,
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data.data;
}
