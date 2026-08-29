import { centralApiClient, PLATFORM_PROVISIONING_TIMEOUT_MS } from "@/lib/api/central-client";
import { normalizeAuthSession } from "@/modules/identity/auth-normalizer";
import type {
  PlatformDashboardResponse,
} from "@/modules/platform/types";
import type { PlatformUser } from "@/stores/platform-auth-store";
import type { AuthSession } from "@/types/auth";

type PlatformLoginPayload = {
  email: string;
  password: string;
};

export type PlatformAuthSession = {
  access_token?: string;
  token_type?: string;
  user?: PlatformUser;
  mfa_required?: boolean;
  mfa_enrollment_required?: boolean;
  login_session_id?: string;
  login_session_expires_at?: string;
  mfa_challenge?: { id: string; expires_at: string };
  recovery_codes?: string[];
};

type PlatformLoginResponse = PlatformAuthSession;

export function platformMicrosoftLoginUrl(): string {
  const base =
    process.env.NEXT_PUBLIC_CENTRAL_API_BASE_URL ??
    process.env.NEXT_PUBLIC_API_BASE_URL ??
    "http://localhost:8000/api/v1";
  return `${base.replace(/\/$/, "")}/platform/auth/microsoft/redirect`;
}

export type PlatformOperatorRow = {
  id: string;
  name: string;
  email: string;
  platform_role: string;
  platform_permissions: string[];
  created_at?: string | null;
};

export async function platformListOperators(): Promise<PlatformOperatorRow[]> {
  const response = await centralApiClient.get<{ data: PlatformOperatorRow[] }>("/platform/operators");
  return response.data.data;
}

export async function platformCreateOperator(payload: {
  name: string;
  email: string;
  password: string;
  platform_role: string;
}): Promise<PlatformOperatorRow> {
  const response = await centralApiClient.post<{ data: PlatformOperatorRow }>("/platform/operators", payload);
  return response.data.data;
}

export async function platformUpdateOperator(
  operatorId: string,
  payload: {
    name?: string;
    email?: string;
    password?: string | null;
    platform_role?: string;
  },
): Promise<PlatformOperatorRow> {
  const response = await centralApiClient.patch<{ data: PlatformOperatorRow }>(
    `/platform/operators/${operatorId}`,
    payload,
  );
  return response.data.data;
}

export async function platformDeleteOperator(operatorId: string): Promise<void> {
  await centralApiClient.delete(`/platform/operators/${operatorId}`);
}

export async function platformFetchRoleCatalog(): Promise<{
  roles: string[];
  permissions: string[];
}> {
  const response = await centralApiClient.get<{
    data: { roles: string[]; permissions: string[] };
  }>("/platform/roles/catalog");
  return response.data.data;
}

export async function platformCreateTenantBillingPortalSession(
  tenantId: string,
): Promise<{ url: string }> {
  const response = await centralApiClient.post<{ data: { url: string } }>(
    `/platform/tenants/${tenantId}/billing-portal-session`,
  );
  return response.data.data;
}

export async function platformLogin(
  payload: PlatformLoginPayload,
): Promise<PlatformLoginResponse> {
  const response = await centralApiClient.post<{ data: PlatformLoginResponse }>(
    "/platform/login",
    payload,
  );
  return response.data.data;
}

export async function platformMfaVerify(payload: {
  login_session_id: string;
  challenge_id: string;
  code: string;
}): Promise<PlatformAuthSession> {
  const response = await centralApiClient.post<{ data: PlatformAuthSession }>(
    "/platform/mfa/verify",
    payload,
  );
  return response.data.data;
}

export async function platformMfaRecovery(payload: {
  login_session_id: string;
  recovery_code: string;
}): Promise<PlatformAuthSession> {
  const response = await centralApiClient.post<{ data: PlatformAuthSession }>(
    "/platform/mfa/recovery",
    payload,
  );
  return response.data.data;
}

export async function platformMfaEnrollStart(loginSessionId: string): Promise<{
  secret: string;
  otpauth_uri: string;
}> {
  const response = await centralApiClient.post<{
    data: { secret: string; otpauth_uri: string };
  }>("/platform/mfa/enroll/start", { login_session_id: loginSessionId });
  return response.data.data;
}

export async function platformMfaEnrollComplete(payload: {
  login_session_id: string;
  code: string;
}): Promise<PlatformAuthSession> {
  const response = await centralApiClient.post<{ data: PlatformAuthSession }>(
    "/platform/mfa/enroll/complete",
    payload,
  );
  return response.data.data;
}

export async function platformMe(): Promise<PlatformUser> {
  const response = await centralApiClient.get<{ data: PlatformUser }>("/platform/me");
  return response.data.data;
}

export type PlatformTenantRow = {
  id: string;
  domains: string[];
  created_at: string | null;
  mfa_required: boolean;
  plan_tier?: string;
  subscription_status?: string;
  seat_limit?: number;
  effective_seat_limit?: number;
  effective_rfi_limit?: number;
  rfi_units_used?: number;
  billing_meter_starts_at?: string | null;
  billing_interval?: "monthly" | "annual";
  billing_overrides?: {
    seat_limit?: number;
    included_paid_seats?: number;
    included_rfi_units?: number;
    grandfather_rfi_units?: number;
    annual_discount_percent?: number | null;
    modules?: {
      e_approval?: { file_uploads?: boolean; max_file_fields?: number | null };
      project_one?: { rollout_file_uploads?: boolean };
      ticketing?: {
        enabled?: boolean;
        file_uploads?: boolean;
        max_attachments_per_ticket?: number | null;
      };
    };
  } | null;
  slug?: string | null;
  brand_domain?: string | null;
  environment?: string | null;
  assigned_playbook_version?: string | null;
  assigned_rollout_policy_code?: string | null;
  assigned_rollout_policy_name?: string | null;
  rollout_policy_bundle_id?: string | null;
  playbook_upgrade_available?: boolean;
  access_mode?: string | null;
  operator_access_mode?: string | null;
  parent_tenant_id?: string | null;
  theme_tokens?: PlatformTenantThemeTokens | null;
  enabled_modules?: string[] | null;
  effective_enabled_modules?: string[];
};

export type PlatformTenantListParams = {
  search?: string;
  environment?: string;
  plan_tier?: string;
  subscription_status?: string;
  modules?: "" | "e_approval_only" | "project_one" | "ticketing";
  access_mode?: "" | "blocked" | "read_only" | "grace";
  /** API field form: `column:asc|desc` (allowlisted physical columns). */
  sort?: string;
  page?: number;
  per_page?: number;
};

export type PlatformTenantListResponse = {
  items: PlatformTenantRow[];
  meta: {
    total: number;
    page: number;
    per_page: number;
    last_page: number;
  };
};

export async function platformListTenants(
  params?: PlatformTenantListParams,
): Promise<PlatformTenantListResponse> {
  const query: Record<string, string | number> = {};
  if (params?.search?.trim()) {
    query.search = params.search.trim();
  }
  if (params?.environment) {
    query.environment = params.environment;
  }
  if (params?.plan_tier) {
    query.plan_tier = params.plan_tier;
  }
  if (params?.subscription_status) {
    query.subscription_status = params.subscription_status;
  }
  if (params?.modules) {
    query.modules = params.modules;
  }
  if (params?.access_mode) {
    query.access_mode = params.access_mode;
  }
  if (params?.sort?.trim()) {
    query.sort = params.sort.trim();
  }
  if (params?.page) {
    query.page = params.page;
  }
  if (params?.per_page) {
    query.per_page = params.per_page;
  }

  const response = await centralApiClient.get<{ data: PlatformTenantListResponse }>(
    "/platform/tenants",
    Object.keys(query).length > 0 ? { params: query } : undefined,
  );
  return response.data.data;
}

export async function platformFetchTenant(tenantId: string): Promise<PlatformTenantRow> {
  const response = await centralApiClient.get<{ data: PlatformTenantRow }>(
    `/platform/tenants/${tenantId}`,
  );
  return response.data.data;
}

export async function platformFetchDashboard(): Promise<PlatformDashboardResponse> {
  const response = await centralApiClient.get<{ data: PlatformDashboardResponse }>(
    "/platform/dashboard",
  );
  return response.data.data;
}

export type PlatformTenantThemeTokens = {
  version: number;
  logo_url?: string | null;
  favicon_url?: string | null;
  light?: Record<string, string>;
  dark?: Record<string, string>;
};

export type PlatformTenantSubscriptionSnapshot = {
  status: string;
  access_mode: "full" | "grace" | "blocked";
  access_allowed: boolean;
  trial_ends_at: string | null;
  past_due_grace_ends_at: string | null;
  canceled_at: string | null;
  subscription_locked_at: string | null;
  days_until_trial_end: number | null;
  days_until_grace_end: number | null;
  message: string | null;
};

export type PlatformTenantSettingsPatch = {
  mfa_required?: boolean;
  theme_tokens?: PlatformTenantThemeTokens | null;
  plan_tier?: "starter" | "professional" | "enterprise";
  subscription_status?: "trial" | "active" | "past_due" | "canceled";
  trial_ends_at?: string | null;
  past_due_grace_ends_at?: string | null;
  seat_limit?: number;
  billing_meter_starts_at?: string | null;
  billing_interval?: "monthly" | "annual";
  confirm_plan_downgrade?: boolean;
  billing_overrides?: {
    seat_limit?: number;
    included_paid_seats?: number;
    included_rfi_units?: number;
    grandfather_rfi_units?: number;
    annual_discount_percent?: number | null;
    modules?: {
      e_approval?: { file_uploads?: boolean; max_file_fields?: number | null };
      project_one?: { rollout_file_uploads?: boolean };
      ticketing?: {
        enabled?: boolean;
        file_uploads?: boolean;
        max_attachments_per_ticket?: number | null;
      };
    };
  } | null;
  enabled_modules?: string[] | null;
  operator_access_mode?: string | null;
};

export type PlatformTenantSettingsResponse = {
  tenant_id: string;
  mfa_required: boolean;
  plan_tier: string;
  subscription_status: string;
  seat_limit: number;
  trial_ends_at?: string | null;
  past_due_grace_ends_at?: string | null;
  subscription?: PlatformTenantSubscriptionSnapshot;
  theme_tokens: PlatformTenantThemeTokens | null;
  enabled_modules?: string[] | null;
  effective_enabled_modules?: string[];
  warnings?: string[];
  payments?: PlatformPaymentsSnapshot;
};

export type PlatformTenantModulesCatalog = {
  platform_modules: string[];
  toggleable_modules: string[];
  required_modules: string[];
  labels: Record<string, string>;
  descriptions?: Record<string, string>;
};

export async function platformFetchTenantModulesCatalog(): Promise<PlatformTenantModulesCatalog> {
  const response = await centralApiClient.get<{ data: PlatformTenantModulesCatalog }>(
    "/platform/tenant-modules/catalog",
  );
  return response.data.data;
}

export type PlatformPlanCatalogTier = {
  plan_tier: string;
  label: string;
  sort: number;
  included?: {
    paid_seats?: number;
    rfi_units?: number;
    storage_gb?: number;
  };
  pricing?: {
    monthly_base_usd?: number;
    annual_base_usd?: number;
    rfi_overage_usd?: number;
    paid_seat_overage_usd?: number;
  };
  annual_discount_percent?: number;
  modules: Record<string, Record<string, unknown>>;
};

export type PlatformPaymentsSnapshot = {
  enabled: boolean;
  configured: boolean;
  operational: boolean;
  publishable_key: string | null;
  self_serve_tiers: string[];
};

export type PlatformBillingCurrencyOption = {
  code: string;
  label: string;
};

export type PlatformPlanCatalogResponse = {
  currency?: string;
  pricing_base_currency?: string;
  exchange_rates?: Record<string, number>;
  supported_currencies?: PlatformBillingCurrencyOption[];
  default_annual_discount_percent?: number;
  tiers: PlatformPlanCatalogTier[];
  payments?: PlatformPaymentsSnapshot;
};

export async function platformFetchPlanCatalog(): Promise<PlatformPlanCatalogResponse> {
  const response = await centralApiClient.get<{ data: PlatformPlanCatalogResponse }>(
    "/platform/billing/plan-catalog",
  );
  return response.data.data;
}

export type PlatformBillingCatalogPatch = {
  currency?: string;
  default_annual_discount_percent?: number;
  tiers?: Array<{
    plan_tier: string;
    annual_discount_percent?: number;
    included?: {
      paid_seats?: number;
      rfi_units?: number;
      storage_gb?: number;
    };
    pricing?: {
      monthly_base_usd?: number;
      rfi_overage_usd?: number;
      paid_seat_overage_usd?: number;
    };
  }>;
};

export async function platformPatchBillingCatalog(
  payload: PlatformBillingCatalogPatch,
): Promise<PlatformPlanCatalogResponse> {
  const response = await centralApiClient.patch<{ data: PlatformPlanCatalogResponse }>(
    "/platform/billing/catalog",
    payload,
  );
  return response.data.data;
}

export type PlatformBillingInsights = {
  currency: string;
  estimated_mrr: number;
  estimated_mrr_note: string;
  revenue_by_tier: Array<{
    plan_tier: string;
    label: string;
    tenant_count: number;
    estimated_mrr: number;
  }>;
  plan_breakdown: Record<string, number>;
  subscription_breakdown: Record<string, number>;
  stripe: PlatformPaymentsSnapshot & { linked_subscriptions: number };
  usage_totals: {
    tenants: number;
    total_seats_used: number;
    total_seat_limit: number;
    tenants_over_limit: number;
    tenants_with_overrides: number;
  };
  enterprise_overrides: Array<{
    id: string;
    slug: string | null;
    plan_tier: string;
    primary_domain: string | null;
    billing_overrides: Record<string, unknown> | null;
  }>;
  recent_billing_activity: Array<{
    id: string;
    tenant_id: string;
    tenant_label: string;
    actor_email: string | null;
    changes: Record<string, unknown>;
    created_at: string | null;
  }>;
  tenant_billing_rows: Array<{
    id: string;
    slug: string | null;
    primary_domain: string | null;
    plan_tier: string;
    plan_label: string;
    subscription_status: string;
    seat_limit: number;
    has_billing_overrides: boolean;
    stripe_subscription_id: string | null;
    estimated_mrr: number;
  }>;
  list_prices: Record<string, number>;
};

export async function platformFetchBillingInsights(): Promise<PlatformBillingInsights> {
  const response = await centralApiClient.get<{ data: PlatformBillingInsights }>(
    "/platform/billing/insights",
  );
  return response.data.data;
}

export async function platformPatchTenantSettings(
  tenantId: string,
  payload: PlatformTenantSettingsPatch,
): Promise<PlatformTenantSettingsResponse> {
  const response = await centralApiClient.patch<{ data: PlatformTenantSettingsResponse }>(
    `/platform/tenants/${tenantId}`,
    payload,
  );
  return response.data.data;
}

export async function platformUpdateTenantMfa(
  tenantId: string,
  payload: { mfa_required: boolean; theme_tokens?: PlatformTenantThemeTokens | null },
): Promise<{
  tenant_id: string;
  mfa_required: boolean;
  theme_tokens: PlatformTenantThemeTokens | null;
}> {
  const data = await platformPatchTenantSettings(tenantId, payload);
  return {
    tenant_id: data.tenant_id,
    mfa_required: data.mfa_required,
    theme_tokens: data.theme_tokens,
  };
}

export async function platformUploadTenantBrandingAsset(
  tenantId: string,
  asset: "logo" | "favicon",
  file: File,
): Promise<PlatformTenantThemeTokens> {
  const form = new FormData();
  form.append("file", file);
  const response = await centralApiClient.post<{ data: { theme_tokens: PlatformTenantThemeTokens } }>(
    `/platform/tenants/${tenantId}/branding/${asset}`,
    form,
    { headers: { "Content-Type": "multipart/form-data" } },
  );

  return response.data.data.theme_tokens;
}

export type PlatformTenantBillingAuditRow = {
  id: string;
  actor_email: string | null;
  changes: Record<string, { from: unknown; to: unknown }>;
  created_at: string | null;
};

export async function platformFetchTenantBillingAudit(
  tenantId: string,
  limit = 50,
): Promise<PlatformTenantBillingAuditRow[]> {
  const response = await centralApiClient.get<{ data: PlatformTenantBillingAuditRow[] }>(
    `/platform/tenants/${tenantId}/billing-audit`,
    { params: { limit } },
  );
  return response.data.data;
}

export type PlatformTenantAuditRow = {
  id: string;
  tenant_id: string | null;
  tenant_slug?: string | null;
  tenant_domain?: string | null;
  event_type: string;
  event_label: string;
  actor_email: string | null;
  changes: Record<string, { from: unknown; to: unknown }> | null;
  metadata: Record<string, unknown> | null;
  created_at: string | null;
};

export async function platformFetchTenantAudit(
  tenantId: string,
  limit = 50,
): Promise<PlatformTenantAuditRow[]> {
  const response = await centralApiClient.get<{ data: PlatformTenantAuditRow[] }>(
    `/platform/tenants/${tenantId}/audit`,
    { params: { limit } },
  );
  return response.data.data;
}

export async function platformFetchRecentAudit(
  limit = 20,
): Promise<PlatformTenantAuditRow[]> {
  const response = await centralApiClient.get<{ data: PlatformTenantAuditRow[] }>(
    "/platform/audit",
    { params: { limit } },
  );
  return response.data.data;
}

export type PlatformTenantUserRow = {
  id: string;
  name: string;
  email: string;
  roles: string[];
  is_active: boolean;
};

export async function platformListTenantUsers(
  tenantId: string,
  limit = 100,
): Promise<PlatformTenantUserRow[]> {
  const response = await centralApiClient.get<{ data: PlatformTenantUserRow[] }>(
    `/platform/tenants/${tenantId}/users`,
    { params: { limit } },
  );
  return response.data.data;
}

export async function platformStartTenantImpersonation(
  tenantId: string,
  payload: { user_id: string; reason: string },
): Promise<AuthSession & { tenant_domain: string | null }> {
  const response = await centralApiClient.post<{
    data: {
      access_token: string;
      refresh_token: string;
      session_id: string;
      mfa_required: boolean;
      tenant_domain: string | null;
      user: Record<string, unknown>;
    };
  }>(`/platform/tenants/${tenantId}/impersonate`, payload);

  const session = normalizeAuthSession(response.data.data);

  return {
    ...session,
    tenant_domain: response.data.data.tenant_domain,
  };
}

export type PlatformTenantBackupRow = {
  id: string;
  tenant_id: string;
  status: string;
  name: string;
  storage_path: string | null;
  byte_size: number | null;
  checksum: string | null;
  database_name: string | null;
  triggered_by: string;
  actor_email: string | null;
  reason: string | null;
  error_message: string | null;
  started_at: string | null;
  finished_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type PlatformTenantBackupListResponse = {
  data: PlatformTenantBackupRow[];
  meta: {
    total: number;
    completed: number;
    storage_bytes: number;
    latest_at: string | null;
    retention_days: number;
  };
};

export async function platformListTenantBackups(
  tenantId: string,
): Promise<PlatformTenantBackupListResponse> {
  const response = await centralApiClient.get<PlatformTenantBackupListResponse>(
    `/platform/tenants/${tenantId}/backups`,
  );
  return {
    data: response.data.data ?? [],
    meta: response.data.meta ?? {
      total: 0,
      completed: 0,
      storage_bytes: 0,
      latest_at: null,
      retention_days: 15,
    },
  };
}

export async function platformCreateTenantBackup(
  tenantId: string,
  payload?: { reason?: string },
): Promise<PlatformTenantBackupRow> {
  const response = await centralApiClient.post<{ data: PlatformTenantBackupRow }>(
    `/platform/tenants/${tenantId}/backups`,
    payload ?? {},
  );
  return response.data.data;
}

export async function platformCronSyncTenantBackup(
  tenantId: string,
): Promise<PlatformTenantBackupRow> {
  const response = await centralApiClient.post<{ data: PlatformTenantBackupRow }>(
    `/platform/tenants/${tenantId}/backups/schedule-run`,
  );
  return response.data.data;
}

export async function platformDownloadTenantBackup(
  tenantId: string,
  backupId: string,
  fileName?: string,
): Promise<void> {
  const response = await centralApiClient.get<Blob>(
    `/platform/tenants/${tenantId}/backups/${backupId}/download`,
    { responseType: "blob" },
  );
  const disposition = String(response.headers["content-disposition"] ?? "");
  const utfMatch = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  const plainMatch = /filename="?([^";]+)"?/i.exec(disposition);
  const resolvedName =
    fileName?.replace(/\.sql\.gz$/i, ".sql").replace(/\.gz$/i, ".sql") ||
    (utfMatch?.[1] ? decodeURIComponent(utfMatch[1]) : null) ||
    plainMatch?.[1] ||
    `${backupId}.sql`;

  const objectUrl = URL.createObjectURL(response.data);
  const anchor = document.createElement("a");
  anchor.href = objectUrl;
  anchor.download = resolvedName;
  anchor.click();
  URL.revokeObjectURL(objectUrl);
}

export async function platformRestoreTenantBackup(
  tenantId: string,
  backupId: string,
  payload: { confirm: string; reason: string },
): Promise<PlatformTenantBackupRow> {
  const response = await centralApiClient.post<{ data: PlatformTenantBackupRow }>(
    `/platform/tenants/${tenantId}/backups/${backupId}/restore`,
    payload,
  );
  return response.data.data;
}

export async function platformDeleteTenantBackup(
  tenantId: string,
  backupId: string,
): Promise<void> {
  await centralApiClient.delete(`/platform/tenants/${tenantId}/backups/${backupId}`);
}

export async function platformDeleteTenant(
  tenantId: string,
  payload: { confirmation: string; cascade?: boolean },
): Promise<{
  tenant_id: string;
  domains_removed: string[];
  database_dropped: boolean;
  filesystem_purged: boolean;
  children_deleted?: string[];
}> {
  const response = await centralApiClient.delete<{
    data: {
      tenant_id: string;
      domains_removed: string[];
      database_dropped: boolean;
      filesystem_purged: boolean;
      children_deleted?: string[];
    };
  }>(`/platform/tenants/${tenantId}`, { data: payload, timeout: PLATFORM_PROVISIONING_TIMEOUT_MS });
  return response.data.data;
}

export type CreateTenantPayload = {
  domain: string;
  tenant_id?: string | null;
  slug?: string | null;
  brand_domain?: string | null;
  environment?: "local" | "test" | "staging" | "production";
  tco_sequence_prefix?: string | null;
  playbook_version_id?: string | null;
  /** null = deployment default; otherwise explicit module list including core + team_access */
  enabled_modules?: string[] | null;
  migrate?: boolean;
  seed?: boolean;
};

export type CreateTenantInitialAdmin = {
  email: string;
  password?: string | null;
  password_generated: boolean;
  password_redacted?: boolean;
  password_from_environment?: boolean;
  hint?: string;
};

export type TenantDomainEndpointRecommendation = {
  purpose: string;
  hostname: string;
  is_primary: boolean;
  login_url: string;
};

export type CreateTenantResponse = {
  tenant_id: string;
  domain: string | null;
  slug?: string | null;
  brand_domain?: string | null;
  environment?: string | null;
  playbook_version?: string | null;
  assigned_policy_code?: string | null;
  domain_endpoints?: TenantDomainEndpointRecommendation[] | null;
  public_holidays_seeded?: number;
  holiday_years?: number[];
  initial_admin?: CreateTenantInitialAdmin;
};

export async function platformCreateTenant(
  payload: CreateTenantPayload,
): Promise<CreateTenantResponse> {
  const response = await centralApiClient.post<{ data: CreateTenantResponse }>(
    "/platform/tenants",
    payload,
    { timeout: PLATFORM_PROVISIONING_TIMEOUT_MS },
  );
  return response.data.data;
}

export type CreateTenantEnvironmentPayload = {
  environment: "local" | "test" | "staging" | "production";
  domain?: string | null;
  migrate?: boolean;
  seed?: boolean;
  enabled_modules?: string[] | null;
  admin_password?: string | null;
};

export type CreateTenantEnvironmentResponse = {
  tenant_id: string;
  source_tenant_id: string;
  org_root_tenant_id: string;
  domain: string | null;
  slug?: string | null;
  brand_domain?: string | null;
  environment?: string | null;
  parent_tenant_id?: string | null;
  playbook_version?: string | null;
  assigned_policy_code?: string | null;
  domain_endpoints?: TenantDomainEndpointRecommendation[] | null;
  public_holidays_seeded?: number;
  holiday_years?: number[];
  initial_admin?: CreateTenantInitialAdmin;
};

export async function platformCreateTenantEnvironment(
  tenantId: string,
  payload: CreateTenantEnvironmentPayload,
): Promise<CreateTenantEnvironmentResponse> {
  const response = await centralApiClient.post<{ data: CreateTenantEnvironmentResponse }>(
    `/platform/tenants/${tenantId}/environments`,
    payload,
    { timeout: PLATFORM_PROVISIONING_TIMEOUT_MS },
  );
  return response.data.data;
}

export type PlatformRolloutPlaybookVersion = {
  id: string;
  version: string;
  name: string;
  sla_working_days_only: boolean;
  published_at: string | null;
};

export type PlatformRolloutPlaybookListResponse = {
  versions: PlatformRolloutPlaybookVersion[];
  registry_versions: string[];
};

export async function platformListRolloutPlaybooks(): Promise<PlatformRolloutPlaybookListResponse> {
  const response = await centralApiClient.get<{ data: PlatformRolloutPlaybookListResponse }>(
    "/platform/rollout-playbooks",
  );
  return response.data.data;
}

export async function platformAssignTenantPlaybook(
  tenantId: string,
  payload: {
    rollout_policy_bundle_id?: string;
    playbook_version_id?: string;
    sync_tenant_database?: boolean;
    upgrade_policy?: "new_rollouts_only" | "include_draft_rollouts";
  },
): Promise<{
  tenant_id: string;
  assigned_version: string;
  assigned_policy_code?: string | null;
  rollout_policy_bundle_id?: string | null;
  upgrade_policy: string;
  assigned_at: string | null;
}> {
  const response = await centralApiClient.post<{
    data: {
      tenant_id: string;
      assigned_version: string;
      assigned_policy_code?: string | null;
      rollout_policy_bundle_id?: string | null;
      upgrade_policy: string;
      assigned_at: string | null;
    };
  }>(`/platform/tenants/${tenantId}/playbook`, payload);
  return response.data.data;
}

export type PlatformRolloutPolicyBundle = {
  id: string;
  code: string;
  name: string;
  status: "draft" | "published";
  playbook_version: string | null;
  playbook_version_id: string;
  timeline_templates: Record<string, Array<Record<string, unknown>>>;
  hidden_phases: Record<string, string[]>;
  gate_approval_policies: Record<string, Record<string, { enabled: boolean; chain: string[] }>>;
  email_notification_policies?: {
    gate_approval: {
      enabled: boolean;
      events: Record<string, { enabled: boolean; recipients: string[] }>;
    };
  };
  delivery_periods: Record<string, { working_days: number; day_one_trigger?: string }>;
  sla_summary: Record<string, { sla_working_days: number; post_day_one_total: number; valid: boolean }>;
  changelog?: string | null;
  published_at?: string | null;
  updated_at?: string | null;
};

export async function platformListRolloutPolicies(status?: string): Promise<PlatformRolloutPolicyBundle[]> {
  const response = await centralApiClient.get<{ data: { policies: PlatformRolloutPolicyBundle[] } }>(
    "/platform/rollout-policies",
    { params: status ? { status } : undefined },
  );
  return response.data.data.policies;
}

export async function platformCreateRolloutPolicyDraft(body: {
  playbook_version_id: string;
  code: string;
  name: string;
}): Promise<PlatformRolloutPolicyBundle> {
  const response = await centralApiClient.post<{ data: PlatformRolloutPolicyBundle }>("/platform/rollout-policies", body);
  return response.data.data;
}

export async function platformFetchRolloutPolicy(id: string): Promise<PlatformRolloutPolicyBundle> {
  const response = await centralApiClient.get<{ data: PlatformRolloutPolicyBundle }>(`/platform/rollout-policies/${id}`);
  return response.data.data;
}

export async function platformUpdateRolloutPolicy(
  id: string,
  body: Partial<PlatformRolloutPolicyBundle>,
): Promise<PlatformRolloutPolicyBundle> {
  const response = await centralApiClient.patch<{ data: PlatformRolloutPolicyBundle }>(
    `/platform/rollout-policies/${id}`,
    body,
  );
  return response.data.data;
}

export async function platformPublishRolloutPolicy(id: string): Promise<PlatformRolloutPolicyBundle> {
  const response = await centralApiClient.post<{ data: PlatformRolloutPolicyBundle }>(
    `/platform/rollout-policies/${id}/publish`,
  );
  return response.data.data;
}

export async function platformPublishRolloutPlaybook(version: string): Promise<{
  id: string;
  version: string;
  name: string;
  published_at: string | null;
}> {
  const response = await centralApiClient.post<{
    data: { id: string; version: string; name: string; published_at: string | null };
  }>("/platform/rollout-playbooks/publish", { version });
  return response.data.data;
}

export type PlatformRolloutCustomPhase = {
  id: string;
  phase_key: string;
  label: string;
  description?: string | null;
  owner_role?: string | null;
  default_anchor: "endorsement" | "tssr_approved";
  default_working_day_start: number;
  default_working_day_end: number;
  default_gate?: string | null;
  counts_toward_sla: boolean;
  applicable_templates: string[];
  is_active: boolean;
  updated_at?: string | null;
};

export async function platformListRolloutCustomPhases(template?: string): Promise<PlatformRolloutCustomPhase[]> {
  const response = await centralApiClient.get<{ data: { phases: PlatformRolloutCustomPhase[] } }>(
    "/platform/rollout-phases",
    { params: template ? { template } : undefined },
  );
  return response.data.data.phases;
}

export async function platformCreateRolloutCustomPhase(body: {
  phase_key: string;
  label: string;
  description?: string;
  owner_role?: string;
  default_anchor?: "endorsement" | "tssr_approved";
  default_working_day_start?: number;
  default_working_day_end?: number;
  default_gate?: string;
  counts_toward_sla?: boolean;
  applicable_templates: string[];
}): Promise<PlatformRolloutCustomPhase> {
  const response = await centralApiClient.post<{ data: PlatformRolloutCustomPhase }>("/platform/rollout-phases", body);
  return response.data.data;
}

export async function platformUpdateRolloutCustomPhase(
  id: string,
  body: Partial<PlatformRolloutCustomPhase>,
): Promise<PlatformRolloutCustomPhase> {
  const response = await centralApiClient.patch<{ data: PlatformRolloutCustomPhase }>(
    `/platform/rollout-phases/${id}`,
    body,
  );
  return response.data.data;
}

export async function platformDeactivateRolloutCustomPhase(id: string): Promise<PlatformRolloutCustomPhase> {
  const response = await centralApiClient.delete<{ data: PlatformRolloutCustomPhase }>(`/platform/rollout-phases/${id}`);
  return response.data.data;
}

export type PlatformOperationalAcronym = {
  id: string;
  acronym: string;
  definition: string;
  category: string | null;
  sort_order: number;
  is_active: boolean;
  updated_at?: string | null;
};

export async function platformListOperationalAcronyms(): Promise<PlatformOperationalAcronym[]> {
  const response = await centralApiClient.get<{ data: PlatformOperationalAcronym[] }>(
    "/platform/operational-acronyms",
  );
  return response.data.data ?? [];
}

export async function platformCreateOperationalAcronym(body: {
  acronym: string;
  definition: string;
  category?: string | null;
  sort_order?: number;
  is_active?: boolean;
}): Promise<PlatformOperationalAcronym> {
  const response = await centralApiClient.post<{ data: PlatformOperationalAcronym }>(
    "/platform/operational-acronyms",
    body,
  );
  return response.data.data;
}

export async function platformUpdateOperationalAcronym(
  id: string,
  body: {
    acronym?: string;
    definition?: string;
    category?: string | null;
    sort_order?: number;
    is_active?: boolean;
  },
): Promise<PlatformOperationalAcronym> {
  const response = await centralApiClient.patch<{ data: PlatformOperationalAcronym }>(
    `/platform/operational-acronyms/${id}`,
    body,
  );
  return response.data.data;
}

export async function platformDeleteOperationalAcronym(id: string): Promise<void> {
  await centralApiClient.delete(`/platform/operational-acronyms/${id}`);
}

export async function platformSyncOperationalAcronymDefaults(): Promise<{ synced: number; message: string }> {
  const response = await centralApiClient.post<{ data: { synced: number; message: string } }>(
    "/platform/operational-acronyms/sync-defaults",
  );
  return response.data.data;
}
