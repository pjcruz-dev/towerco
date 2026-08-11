import { apiClient } from "@/lib/api/client";
import type { PaginatedEnvelope, PaginatedMeta } from "@/lib/api/paginated";
import type { FiberOneDashboardResponse, FiberRouteListRow } from "@/modules/fiber-one/types";

export async function fetchFiberOneDashboard(): Promise<FiberOneDashboardResponse> {
  const response = await apiClient.get<{ data: FiberOneDashboardResponse }>("/fiber-one/dashboard");
  return response.data.data;
}

export async function fetchFiberOneRoutesIndex(params: {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
}): Promise<PaginatedEnvelope<FiberRouteListRow>> {
  const response = await apiClient.get<{ data: FiberRouteListRow[]; meta: PaginatedMeta }>("/fiber-one/routes", {
    params,
  });

  return { data: response.data.data, meta: response.data.meta };
}
