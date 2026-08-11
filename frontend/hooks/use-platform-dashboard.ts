"use client";

import { useQuery } from "@tanstack/react-query";

import { platformFetchDashboard } from "@/lib/api/modules/platform-api";
import type { PlatformDashboardResponse } from "@/modules/platform/types";

export const platformDashboardEmptyState: PlatformDashboardResponse = {
  environment: "local",
  kpis: [],
  environment_breakdown: {},
  subscription_breakdown: {},
  plan_breakdown: {},
  actions: [],
  health_summary: { healthy: 0, database_missing: 0, migrations_pending: 0 },
  health_issues: [],
  seat_summary: {
    total_seats_used: 0,
    total_seat_limit: 0,
    tenants_over_limit: 0,
    tenants_near_limit: 0,
  },
  seat_usage: [],
  subscription_alerts: [],
  provisioning_trend: [],
  brand_breakdown: {},
  recent_tenants: [],
  recent_audit: [],
};

export function usePlatformDashboard(enabled: boolean) {
  return useQuery({
    queryKey: ["platform", "dashboard"],
    queryFn: platformFetchDashboard,
    enabled,
    staleTime: 60_000,
    placeholderData: platformDashboardEmptyState,
  });
}
