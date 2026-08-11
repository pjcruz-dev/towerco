"use client";

import { useQuery } from "@tanstack/react-query";

import {
  fetchTenantNotificationUnreadCount,
  fetchTenantNotificationsIndex,
  type TenantNotificationsIndexParams,
} from "@/lib/api/modules/tenant-notifications-api";

export const tenantNotificationQueryKeys = {
  unreadCount: ["tenant", "notifications", "unread-count"] as const,
  list: (params?: TenantNotificationsIndexParams) =>
    ["tenant", "notifications", "list", params ?? {}] as const,
};

export function useTenantNotificationUnreadCount(enabled = true) {
  return useQuery({
    queryKey: tenantNotificationQueryKeys.unreadCount,
    queryFn: fetchTenantNotificationUnreadCount,
    enabled,
    staleTime: 60_000,
    // Relaxed from 60s to 2m: rollout Echo events invalidate ["tenant","notifications"] and we
    // still refetch on window focus, so the badge stays reasonably fresh with less polling.
    refetchInterval: 120_000,
    refetchOnWindowFocus: true,
  });
}

export function useTenantNotificationsIndex(
  params: TenantNotificationsIndexParams,
  enabled = true,
) {
  return useQuery({
    queryKey: tenantNotificationQueryKeys.list(params),
    queryFn: () => fetchTenantNotificationsIndex(params),
    enabled,
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  });
}

/** Recent notifications for the header popover (first page, up to 50). */
export function useTenantNotifications(enabled = true) {
  return useTenantNotificationsIndex({ page: 1, per_page: 50 }, enabled);
}
