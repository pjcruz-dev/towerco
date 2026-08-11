import { apiClient } from "@/lib/api/client";
import type { WorkspaceDashboardResponse } from "@/modules/workspace/types";

export const emptyWorkspaceDashboard: WorkspaceDashboardResponse = {
  environment: "local",
  kpis: [],
  actions: [],
  awaiting_me: { total: 0, items: [] },
  recent_activity: [],
  quick_links: [],
};

function normalizeWorkspaceDashboard(
  payload: Partial<WorkspaceDashboardResponse> | null | undefined,
): WorkspaceDashboardResponse {
  return {
    environment: payload?.environment ?? emptyWorkspaceDashboard.environment,
    kpis: Array.isArray(payload?.kpis) ? payload.kpis : [],
    actions: Array.isArray(payload?.actions) ? payload.actions : [],
    awaiting_me: {
      total: Number(payload?.awaiting_me?.total ?? 0),
      items: Array.isArray(payload?.awaiting_me?.items) ? payload.awaiting_me.items : [],
    },
    recent_activity: Array.isArray(payload?.recent_activity) ? payload.recent_activity : [],
    quick_links: Array.isArray(payload?.quick_links) ? payload.quick_links : [],
  };
}

export async function fetchWorkspaceDashboard(): Promise<WorkspaceDashboardResponse> {
  const response = await apiClient.get<{ data: Partial<WorkspaceDashboardResponse> }>("/dashboard");
  return normalizeWorkspaceDashboard(response.data.data);
}
