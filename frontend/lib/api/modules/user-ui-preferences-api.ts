import { apiClient } from "@/lib/api/client";

export type UserUiPreferenceSharedPayload = {
  layouts?: unknown;
  layout?: Record<string, unknown> | null;
  updated_by?: string | null;
  updated_at?: string | null;
};

export type UserUiPreferencePayload = {
  key: string;
  value: Record<string, unknown> | null;
  shared?: UserUiPreferenceSharedPayload | null;
};

export async function fetchUserUiPreference(key: string): Promise<Record<string, unknown> | null> {
  const response = await apiClient.get<{ data: UserUiPreferencePayload }>(
    `/me/ui-preferences/${encodeURIComponent(key)}`,
  );
  return response.data.data.value ?? null;
}

export async function fetchUserUiPreferenceBundle(key: string): Promise<UserUiPreferencePayload> {
  const response = await apiClient.get<{ data: UserUiPreferencePayload }>(
    `/me/ui-preferences/${encodeURIComponent(key)}`,
  );
  return response.data.data;
}

export async function putUserUiPreference(
  key: string,
  value: Record<string, unknown>,
): Promise<Record<string, unknown>> {
  const response = await apiClient.put<{ data: UserUiPreferencePayload }>(
    `/me/ui-preferences/${encodeURIComponent(key)}`,
    { value },
  );
  return response.data.data.value ?? {};
}

export async function deleteUserUiPreference(key: string): Promise<void> {
  await apiClient.delete(`/me/ui-preferences/${encodeURIComponent(key)}`);
}

export async function putSharedUserUiPreference(
  key: string,
  value: { layouts: unknown[] } | { layout: Record<string, unknown> },
): Promise<UserUiPreferenceSharedPayload | null> {
  const response = await apiClient.put<{ data: UserUiPreferencePayload }>(
    `/me/ui-preferences/${encodeURIComponent(key)}/shared`,
    { value },
  );
  return response.data.data.shared ?? null;
}

export type SharedUiLayoutAuditItem = {
  key: string;
  kind: "dashboard-layout" | "module-list";
  summary: string;
  updated_by: string | null;
  updated_by_name: string | null;
  updated_at: string | null;
};

export async function fetchSharedUiLayoutAudit(): Promise<SharedUiLayoutAuditItem[]> {
  const response = await apiClient.get<{ data: { items: SharedUiLayoutAuditItem[] } }>(
    "/admin/shared-ui-layouts",
  );
  return Array.isArray(response.data.data.items) ? response.data.data.items : [];
}

export async function deleteSharedUserUiPreference(key: string): Promise<void> {
  await apiClient.delete(`/me/ui-preferences/${encodeURIComponent(key)}/shared`);
}
