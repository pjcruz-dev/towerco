import type { PaginatedMeta } from "@/lib/api/paginated";
import type { TenantNotificationRow } from "@/modules/notifications/types";
import { apiClient } from "@/lib/api/client";

export type NotificationShellParam = "edcs" | "e_approval" | "ticketing" | "asset_management";

export type TenantNotificationsIndexParams = {
  page?: number;
  per_page?: number;
  category?: "action" | "update";
  module?: "e_approval" | "ticketing" | "documents";
  shell?: NotificationShellParam;
  unread_only?: boolean;
};

export async function fetchTenantNotificationsIndex(
  params: TenantNotificationsIndexParams = {},
): Promise<{ data: TenantNotificationRow[]; meta: PaginatedMeta }> {
  const response = await apiClient.get<{ data: TenantNotificationRow[]; meta: PaginatedMeta }>(
    "/notifications",
    {
      params: {
        page: params.page,
        per_page: params.per_page,
        category: params.category,
        module: params.module,
        shell: params.shell,
        unread_only: params.unread_only ? 1 : undefined,
      },
    },
  );
  return response.data;
}

export async function fetchTenantNotificationUnreadCount(shell?: NotificationShellParam | null): Promise<number> {
  const response = await apiClient.get<{ data: { count: number } }>("/notifications/unread-count", {
    params: shell ? { shell } : undefined,
  });
  return response.data.data.count;
}

export async function markTenantNotificationRead(id: string): Promise<void> {
  await apiClient.post(`/notifications/${id}/read`);
}

export async function markAllTenantNotificationsRead(
  options?: {
    category?: "action" | "update";
    module?: "e_approval" | "ticketing" | "documents";
    shell?: NotificationShellParam;
  },
): Promise<void> {
  await apiClient.post("/notifications/mark-all-read", options ?? undefined);
}
