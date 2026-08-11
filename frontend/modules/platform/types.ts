import type { ProjectOneKpi } from "@/modules/project-one/types";

export type PlatformDashboardKpi = ProjectOneKpi;

export type PlatformDashboardAction = {
  id: string;
  label: string;
  count: number;
  href: string;
  priority: "normal" | "high";
};

export type PlatformDashboardRecentTenant = {
  id: string;
  slug?: string | null;
  environment?: string | null;
  primary_domain?: string | null;
  created_at?: string | null;
  mfa_required?: boolean;
  playbook_upgrade_available?: boolean;
};

export type PlatformDashboardAuditRow = {
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

export type PlatformHealthSummary = {
  healthy: number;
  database_missing: number;
  migrations_pending: number;
};

export type PlatformHealthIssue = {
  id: string;
  slug?: string | null;
  primary_domain?: string | null;
  issue: "missing_database" | "migrations_pending";
  detail: string;
  pending_migrations?: number;
};

export type PlatformSeatSummary = {
  total_seats_used: number;
  total_seat_limit: number;
  tenants_over_limit: number;
  tenants_near_limit: number;
};

export type PlatformSeatUsageRow = {
  id: string;
  slug?: string | null;
  label: string;
  primary_domain?: string | null;
  environment?: string | null;
  seat_used: number;
  seat_limit: number;
  utilization_percent: number;
  over_limit: boolean;
};

export type PlatformSubscriptionAlert = {
  id: string;
  slug?: string | null;
  primary_domain?: string | null;
  subscription_status: string;
  plan_tier: string;
  environment?: string | null;
};

export type PlatformProvisioningTrendPoint = {
  week_start: string;
  label: string;
  count: number;
};

export type PlatformDashboardResponse = {
  environment: string;
  latest_playbook_version?: string | null;
  kpis: PlatformDashboardKpi[];
  environment_breakdown: Record<string, number>;
  subscription_breakdown: Record<string, number>;
  plan_breakdown: Record<string, number>;
  actions: PlatformDashboardAction[];
  health_summary: PlatformHealthSummary;
  health_issues: PlatformHealthIssue[];
  seat_summary: PlatformSeatSummary;
  seat_usage: PlatformSeatUsageRow[];
  subscription_alerts: PlatformSubscriptionAlert[];
  provisioning_trend: PlatformProvisioningTrendPoint[];
  brand_breakdown: Record<string, number>;
  recent_tenants: PlatformDashboardRecentTenant[];
  recent_audit: PlatformDashboardAuditRow[];
};
