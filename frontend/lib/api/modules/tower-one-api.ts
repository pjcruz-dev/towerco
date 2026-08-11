import { apiClient } from "@/lib/api/client";
import type { PaginatedEnvelope, PaginatedMeta } from "@/lib/api/paginated";
import type { TowerDetail, TowerListRow, TowerOneDashboardResponse } from "@/modules/tower-one/types";

export async function fetchTowerOneDashboard(): Promise<TowerOneDashboardResponse> {
  const response = await apiClient.get<{ data: TowerOneDashboardResponse }>("/tower-one/dashboard");
  return response.data.data;
}

export async function fetchTowerOneTowersIndex(params: {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
}): Promise<PaginatedEnvelope<TowerListRow>> {
  const response = await apiClient.get<{ data: TowerListRow[]; meta: PaginatedMeta }>("/tower-one/towers", {
    params,
  });

  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchTowerOneTower(towerId: string): Promise<TowerDetail> {
  const response = await apiClient.get<{ data: TowerDetail }>(`/tower-one/towers/${towerId}`);
  return response.data.data;
}
