"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchAssetOneAsset } from "@/lib/api/modules/asset-one-api";

export function useAssetOneAssetDetail(assetId: string) {
  return useQuery({
    queryKey: ["asset-one", "assets", "detail", assetId],
    queryFn: () => fetchAssetOneAsset(assetId),
    enabled: Boolean(assetId),
  });
}
