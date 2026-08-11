import { apiClient } from "@/lib/api/client";
import type { PlanCatalogTier } from "@/components/billing/plan-tier-comparison-table";
import type { ProcurementPlanFeatures } from "@/modules/procurement-one/types";

export type TenantSubscriptionSnapshot = {
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

export type TenantBillingSnapshot = {
  tenant_id: string;
  currency?: string;
  plan_tier: string;
  plan_label?: string;
  subscription_status: string;
  subscription?: TenantSubscriptionSnapshot;
  seat_limit: number;
  seat_used: number;
  viewer_seats_used?: number;
  seats_available: number;
  rfi_units?: {
    used: number;
    limit: number;
    available: number;
    metering_active: boolean;
  };
  billing_meter_starts_at?: string | null;
  billing_interval?: "monthly" | "annual";
  annual_discount_percent?: number | null;
  entitlements?: PlanCatalogTier["modules"];
  plan_features: {
    file_uploads: boolean;
    max_file_fields: number | null;
    procurement_one?: ProcurementPlanFeatures;
  };
  plan_catalog?: { currency?: string; tiers: PlanCatalogTier[] };
  support_email?: string | null;
  payments?: TenantPaymentsSnapshot;
  has_enterprise_overrides?: boolean;
  billing_overrides?: Record<string, unknown> | null;
  billing_estimate?: TenantBillingEstimateSnapshot | null;
  overage?: TenantBillingEstimateSnapshot | null;
};

export type TenantBillingEstimateSnapshot = {
  currency: string;
  pricing_base_currency?: string;
  billing_interval: "monthly" | "annual";
  annual_discount_percent: number;
  monthly_base: number;
  catalog_included_paid_seats: number;
  catalog_included_rfi_units: number;
  effective_paid_seats: number;
  paid_rfi_capacity: number;
  grandfather_rfi_units?: number;
  committed_extra_seats: number;
  committed_extra_rfi_units: number;
  billable_extra_seats: number;
  billable_extra_rfi_units: number;
  per_paid_seat_monthly: number;
  per_rfi_unit_monthly: number;
  add_one_paid_seat_monthly?: number;
  add_one_rfi_unit_monthly?: number;
  seat_addons_monthly: number;
  rfi_addons_monthly: number;
  addons_monthly: number;
  estimated_monthly_total: number;
  annual_base_prepaid: number;
  annual_addons_estimate: number;
  estimated_annual_total: number;
  estimated_amount_due: number;
  addons_billed_monthly_note: string | null;
  estimated_monthly_overage?: number;
};

export type TenantPaymentsSnapshot = {
  enabled: boolean;
  configured: boolean;
  operational: boolean;
  publishable_key: string | null;
  self_serve_tiers: string[];
  has_stripe_customer: boolean;
  has_active_subscription: boolean;
  upgrade_options: Array<{ plan_tier: string; label: string }>;
};

export async function createTenantBillingCheckoutSession(
  planTier: string,
): Promise<{ url: string; session_id: string }> {
  const response = await apiClient.post<{ data: { url: string; session_id: string } }>(
    "/admin/billing/checkout-session",
    { plan_tier: planTier },
  );
  return response.data.data;
}

export async function createTenantBillingPortalSession(): Promise<{ url: string }> {
  const response = await apiClient.post<{ data: { url: string } }>(
    "/admin/billing/portal-session",
  );
  return response.data.data;
}

export async function fetchTenantBilling(): Promise<TenantBillingSnapshot> {
  const response = await apiClient.get<{ data: TenantBillingSnapshot }>("/admin/billing");
  return response.data.data;
}

export type TenantBillingUsageReport = {
  tenant_id: string;
  plan_tier: string;
  period_days: number;
  period_start: string;
  seats: { used: number; limit: number; total_users: number };
  modules: {
    e_approval: {
      forms_total: number;
      forms_published: number;
      submissions_total: number;
      submissions_last_30d: number;
    };
    project_one: { rollouts_total: number; rollouts_last_30d: number };
  };
  has_enterprise_overrides: boolean;
};

export async function fetchTenantBillingUsage(): Promise<TenantBillingUsageReport> {
  const response = await apiClient.get<{ data: TenantBillingUsageReport }>("/admin/billing/usage");
  return response.data.data;
}
