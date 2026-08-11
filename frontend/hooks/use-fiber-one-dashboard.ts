"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchFiberOneDashboard } from "@/lib/api/modules/fiber-one-api";
import type { FiberOneDashboardResponse } from "@/modules/fiber-one/types";

const emptyState: FiberOneDashboardResponse = {
  kpis: [],
  routes: [],
};

export function useFiberOneDashboard() {
  return useQuery({
    queryKey: ["fiber-one", "dashboard"],
    queryFn: fetchFiberOneDashboard,
    staleTime: 60_000,
    placeholderData: emptyState,
  });
}
