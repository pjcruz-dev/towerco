"use client";

import { useQuery } from "@tanstack/react-query";

import {
  fetchEApprovalNotificationUnreadCount,
  fetchEApprovalNotificationsIndex,
  type EApprovalNotificationsIndexParams,
} from "@/lib/api/modules/e-approval-api";
import type { EApprovalNotificationTab } from "@/modules/e-approval/notification-display";

export const eApprovalNotificationQueryKeys = {
  unreadCount: ["e-approval", "notifications", "unread-count"] as const,
  list: (params?: EApprovalNotificationsIndexParams) =>
    ["e-approval", "notifications", "list", params ?? {}] as const,
};

export function useEApprovalNotificationUnreadCount(enabled = true) {
  return useQuery({
    queryKey: eApprovalNotificationQueryKeys.unreadCount,
    queryFn: fetchEApprovalNotificationUnreadCount,
    enabled,
    staleTime: 30_000,
    refetchInterval: 60_000,
    refetchOnWindowFocus: true,
  });
}

export function useEApprovalNotificationsIndex(
  params: EApprovalNotificationsIndexParams,
  enabled = true,
) {
  return useQuery({
    queryKey: eApprovalNotificationQueryKeys.list(params),
    queryFn: () => fetchEApprovalNotificationsIndex(params),
    enabled,
    staleTime: 30_000,
    refetchOnWindowFocus: true,
  });
}

/** Recent notifications for the header popover (first page, up to 50). */
export function useEApprovalNotifications(enabled = true) {
  return useEApprovalNotificationsIndex({ page: 1, per_page: 50 }, enabled);
}
