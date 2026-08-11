"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchAssetOneDashboard } from "@/lib/api/modules/asset-one-api";
import type { AssetOneDashboardResponse } from "@/modules/asset-one/types";

const emptyState: AssetOneDashboardResponse = {
  kpis: [],
  by_category: [],
  assets: [],
};

export function useAssetOneDashboard() {
  return useQuery({
    queryKey: ["asset-one", "dashboard"],
    queryFn: fetchAssetOneDashboard,
    staleTime: 60_000,
    placeholderData: emptyState,
  });
}
