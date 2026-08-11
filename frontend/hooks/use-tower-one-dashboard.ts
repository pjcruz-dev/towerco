"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchTowerOneDashboard } from "@/lib/api/modules/tower-one-api";
import type { TowerOneDashboardResponse } from "@/modules/tower-one/types";

const emptyState: TowerOneDashboardResponse = {
  kpis: [],
  towers: [],
};

export function useTowerOneDashboard() {
  return useQuery({
    queryKey: ["tower-one", "dashboard"],
    queryFn: fetchTowerOneDashboard,
    staleTime: 60_000,
    placeholderData: emptyState,
  });
}
