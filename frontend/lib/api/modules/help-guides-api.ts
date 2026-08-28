import { apiClient } from "@/lib/api/client";

export type HelpGuideRole = "requestor" | "approver" | "all";

export type HelpGuideListRow = {
  id: string;
  module_key: string;
  slug: string;
  role: HelpGuideRole;
  title: string;
  status: "draft" | "published";
  sort_order: number;
  updated_at: string | null;
};

export type HelpGuideDetail = HelpGuideListRow & {
  body: string;
  content_checksum: string | null;
  created_at: string | null;
};

export async function fetchPublishedHelpGuides(params?: {
  module?: string;
  role?: HelpGuideRole;
}): Promise<HelpGuideListRow[]> {
  const response = await apiClient.get<{ data: HelpGuideListRow[] }>("/help/guides", {
    params: {
      ...(params?.module ? { module: params.module } : {}),
      ...(params?.role ? { role: params.role } : {}),
    },
  });
  return response.data.data;
}

export async function fetchPublishedHelpGuide(slug: string): Promise<HelpGuideDetail> {
  const response = await apiClient.get<{ data: HelpGuideDetail }>(`/help/guides/${slug}`);
  return response.data.data;
}

export async function fetchAdminHelpGuides(module = "e_approval"): Promise<HelpGuideListRow[]> {
  const response = await apiClient.get<{ data: HelpGuideListRow[] }>("/help/admin/guides", {
    params: { module },
  });
  return response.data.data;
}

export async function fetchAdminHelpGuide(slug: string): Promise<HelpGuideDetail> {
  const response = await apiClient.get<{ data: HelpGuideDetail }>(`/help/admin/guides/${slug}`);
  return response.data.data;
}

export async function updateAdminHelpGuide(
  slug: string,
  payload: {
    title?: string;
    body?: string;
    role?: HelpGuideRole;
    sort_order?: number;
  },
): Promise<HelpGuideDetail> {
  const response = await apiClient.put<{ data: HelpGuideDetail }>(`/help/admin/guides/${slug}`, payload);
  return response.data.data;
}

export async function publishAdminHelpGuide(slug: string): Promise<HelpGuideDetail> {
  const response = await apiClient.post<{ data: HelpGuideDetail }>(`/help/admin/guides/${slug}/publish`);
  return response.data.data;
}

export async function unpublishAdminHelpGuide(slug: string): Promise<HelpGuideDetail> {
  const response = await apiClient.post<{ data: HelpGuideDetail }>(`/help/admin/guides/${slug}/unpublish`);
  return response.data.data;
}
