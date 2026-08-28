import { apiClient } from "@/lib/api/client";
import type { PaginatedMeta } from "@/lib/api/paginated";

export type WorkspaceAuditChange = {
  from: unknown;
  to: unknown;
};

export type WorkspaceAuditCategory = "security" | "access" | "data_change" | "lifecycle";
export type WorkspaceAuditSeverity = "low" | "medium" | "high" | "critical";

export type WorkspaceAuditRow = {
  id: string;
  source: string;
  module: string;
  action: string;
  action_label?: string;
  action_family?: string;
  category?: WorkspaceAuditCategory | string | null;
  severity?: WorkspaceAuditSeverity | string | null;
  summary: string | null;
  reason?: string | null;
  entity_type: string | null;
  entity_id: string | null;
  entity_label: string | null;
  actor: { id: string; name: string; email: string } | null;
  ip_address: string | null;
  user_agent?: string | null;
  metadata: Record<string, unknown> | null;
  changes?: Record<string, WorkspaceAuditChange> | null;
  created_at: string | null;
  href: string | null;
};

export type WorkspaceAuditQueryParams = {
  page?: number;
  per_page?: number;
  module?: string;
  search?: string;
  actor?: string;
  from?: string;
  to?: string;
  sort?: string;
  category?: string;
  severity?: string;
  action_family?: string;
  entity_type?: string;
  entity_id?: string;
};

export async function fetchWorkspaceAuditIndex(
  params?: WorkspaceAuditQueryParams,
): Promise<{ data: WorkspaceAuditRow[]; meta: PaginatedMeta }> {
  const response = await apiClient.get<{ data: WorkspaceAuditRow[]; meta: PaginatedMeta }>("/workspace/audit", {
    params,
  });
  return response.data;
}

export async function fetchWorkspaceAuditForEntity(params: {
  entity_type: string;
  entity_id: string;
  limit?: number;
}): Promise<WorkspaceAuditRow[]> {
  const response = await apiClient.get<{ data: WorkspaceAuditRow[] }>("/workspace/audit/entity", {
    params,
  });
  return response.data.data;
}

export async function exportWorkspaceAuditCsv(
  params?: Omit<WorkspaceAuditQueryParams, "page" | "per_page" | "sort">,
): Promise<Blob> {
  const response = await apiClient.get<Blob>("/workspace/audit/export", {
    params,
    responseType: "blob",
  });
  return response.data;
}
