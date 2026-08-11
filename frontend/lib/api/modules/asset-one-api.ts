import { apiClient } from "@/lib/api/client";
import type { PaginatedEnvelope, PaginatedMeta } from "@/lib/api/paginated";
import type { AssetDetail, AssetListRow, AssetOneDashboardResponse } from "@/modules/asset-one/types";

export async function fetchAssetOneDashboard(): Promise<AssetOneDashboardResponse> {
  const response = await apiClient.get<{ data: AssetOneDashboardResponse }>("/asset-one/dashboard");
  return response.data.data;
}

export async function fetchAssetOneAssetsIndex(params: {
  page?: number;
  per_page?: number;
  search?: string;
  sort?: string;
}): Promise<PaginatedEnvelope<AssetListRow>> {
  const response = await apiClient.get<{ data: AssetListRow[]; meta: PaginatedMeta }>("/asset-one/assets", {
    params,
  });

  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchAssetOneAsset(assetId: string): Promise<AssetDetail> {
  const response = await apiClient.get<{ data: AssetDetail }>(`/asset-one/assets/${assetId}`);
  return response.data.data;
}
