import { apiClient } from "@/lib/api/client";

export type TenantMicrosoftSsoConfig = {
  id: string;
  provider: string;
  issuer: string | null;
  client_id: string;
  has_client_secret: boolean;
  tenant_identifier: string;
  group_mapping_rules: Record<string, string[]>;
  allowed_email_domains: string[];
  auto_provision_users: boolean;
  disable_password_login_when_enabled: boolean;
  enabled: boolean;
  redirect_uri: string;
  login_redirect_path: string;
};

export type TenantMicrosoftSsoConfigInput = {
  issuer?: string | null;
  client_id: string;
  client_secret?: string;
  tenant_identifier?: string;
  group_mapping_rules?: Record<string, string[]>;
  allowed_email_domains?: string[];
  auto_provision_users?: boolean;
  disable_password_login_when_enabled?: boolean;
  enabled?: boolean;
};

export type MicrosoftSignInPublicStatus = {
  enabled: boolean;
  provider: string;
  redirect_path: string;
  label: string;
} | null;

export type PasswordLoginPublicStatus = {
  available: boolean;
  restricted_when_sso_enabled: boolean;
} | null;

export type PasskeysPublicStatus = {
  enabled: boolean;
  label: string;
  policy: "allow" | "prefer" | "require";
  satisfies_mfa: boolean;
} | null;

export type TenantAuthPublicStatus = {
  microsoft_sign_in: MicrosoftSignInPublicStatus;
  password_login: PasswordLoginPublicStatus;
  passkeys: PasskeysPublicStatus;
};

export async function fetchTenantMicrosoftSsoConfig(): Promise<TenantMicrosoftSsoConfig | null> {
  const response = await apiClient.get<{ data: TenantMicrosoftSsoConfig | null }>("/admin/sso/config");
  return response.data.data;
}

export async function updateTenantMicrosoftSsoConfig(
  payload: TenantMicrosoftSsoConfigInput,
): Promise<TenantMicrosoftSsoConfig | null> {
  const response = await apiClient.put<{ data: { config: TenantMicrosoftSsoConfig | null } }>(
    "/admin/sso/config",
    payload,
  );
  return response.data.data.config;
}

export async function testTenantMicrosoftSsoConnection(payload: {
  client_id: string;
  tenant_identifier: string;
  client_secret?: string;
}): Promise<{ ok: boolean; message: string; redirect_uri?: string }> {
  const response = await apiClient.post<{
    data: { ok: boolean; message: string; redirect_uri?: string };
  }>("/admin/sso/test-connection", payload);
  return response.data.data;
}

export async function fetchTenantAuthPublicStatus(): Promise<TenantAuthPublicStatus> {
  const response = await apiClient.get<{ data: TenantAuthPublicStatus }>("/auth/sso/azure/status");
  return response.data.data;
}

export type TenantSecuritySettings = {
  mfa_required: boolean;
  mfa_trust_days: number;
  mfa_global_enabled: boolean;
  mfa_policy_active: boolean;
  passkeys_enabled: boolean;
  passkeys_global_enabled: boolean;
  passkeys_default_enabled: boolean;
  passkeys_policy: "allow" | "prefer" | "require";
  passkeys_satisfies_mfa: boolean;
};

export async function fetchTenantSecuritySettings(): Promise<TenantSecuritySettings> {
  const response = await apiClient.get<{ data: TenantSecuritySettings }>("/admin/security");
  return response.data.data;
}

export async function updateTenantSecuritySettings(payload: {
  mfa_required: boolean;
  mfa_trust_days?: number;
  passkeys_enabled?: boolean;
  passkeys_policy?: "allow" | "prefer" | "require";
  passkeys_satisfies_mfa?: boolean;
}): Promise<TenantSecuritySettings> {
  const response = await apiClient.patch<{ data: TenantSecuritySettings }>("/admin/security", payload);
  return response.data.data;
}

export async function fetchMicrosoftSignInStatus(): Promise<MicrosoftSignInPublicStatus> {
  const status = await fetchTenantAuthPublicStatus();
  return status.microsoft_sign_in;
}
