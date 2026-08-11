import type { PaginatedMeta } from "@/lib/api/paginated";
import type { TenantNotificationRow } from "@/modules/notifications/types";
import { apiClient } from "@/lib/api/client";

export type TenantNotificationsIndexParams = {
  page?: number;
  per_page?: number;
  category?: "action" | "update";
  module?: "e_approval" | "project_one";
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
        unread_only: params.unread_only ? 1 : undefined,
      },
    },
  );
  return response.data;
}

export async function fetchTenantNotificationUnreadCount(): Promise<number> {
  const response = await apiClient.get<{ data: { count: number } }>("/notifications/unread-count");
  return response.data.data.count;
}

export async function markTenantNotificationRead(id: string): Promise<void> {
  await apiClient.post(`/notifications/${id}/read`);
}

export async function markAllTenantNotificationsRead(
  options?: { category?: "action" | "update"; module?: "e_approval" | "project_one" },
): Promise<void> {
  await apiClient.post("/notifications/mark-all-read", options ?? undefined);
}
