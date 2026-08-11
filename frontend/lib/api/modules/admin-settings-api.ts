import { apiClient } from "@/lib/api/client";

export type AdminSettingsPayload = {
  kpi_config: Record<string, unknown> | null;
  sla_config: Record<string, unknown> | null;
  workflow_templates: Record<string, unknown>[] | null;
};

export type AdminSettingsUpdatePayload = {
  kpi_config?: Record<string, unknown> | null;
  sla_config?: Record<string, unknown> | null;
  workflow_templates?: Record<string, unknown>[] | null;
};

export async function fetchAdminSettings(): Promise<AdminSettingsPayload> {
  const response = await apiClient.get<{ data: AdminSettingsPayload }>("/admin/settings");
  return response.data.data;
}

export async function updateAdminSettings(payload: AdminSettingsUpdatePayload): Promise<AdminSettingsPayload> {
  const response = await apiClient.patch<{ data: AdminSettingsPayload }>("/admin/settings", payload);
  return response.data.data;
}
