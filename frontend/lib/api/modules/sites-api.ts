import { apiClient } from "@/lib/api/client";
import type { PaginatedEnvelope, PaginatedMeta } from "@/lib/api/paginated";
import type { SiteDetail, SiteListRow } from "@/modules/sites/types";

export async function fetchSitesIndex(params: {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
}): Promise<PaginatedEnvelope<SiteListRow>> {
  const response = await apiClient.get<{ data: SiteListRow[]; meta: PaginatedMeta }>("/sites", { params });

  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchSite(siteId: string): Promise<SiteDetail> {
  const response = await apiClient.get<{ data: SiteDetail }>(`/sites/${siteId}`);
  return response.data.data;
}
