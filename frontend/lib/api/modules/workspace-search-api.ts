import { apiClient } from "@/lib/api/client";

export type WorkspaceSearchResult = {
  module: string;
  entity_type: string;
  id: string;
  title: string;
  subtitle: string | null;
  status: string | null;
  /** Friendly label when the API provides one (e.g. returned → Needs revision). */
  status_label?: string | null;
  /** E-Approval current workflow step (when in flight). */
  current_step?: number | null;
  /** Short “who is blocking” label (approver names or document control). */
  waiting_on?: string | null;
  href: string;
};

export async function fetchWorkspaceSearch(
  query: string,
  limit = 4,
  signal?: AbortSignal,
): Promise<WorkspaceSearchResult[]> {
  const trimmed = query.trim();
  if (trimmed.length < 2) {
    return [];
  }

  const response = await apiClient.get<{ data: WorkspaceSearchResult[] }>("/workspace/search", {
    params: {
      q: trimmed,
      limit,
    },
    signal,
  });

  return response.data.data ?? [];
}
