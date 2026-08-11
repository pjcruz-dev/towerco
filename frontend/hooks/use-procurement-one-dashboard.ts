"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchProcurementOneDashboard } from "@/lib/api/modules/procurement-one-api";
import type { ProcurementOneDashboardResponse } from "@/modules/procurement-one/types";

const emptyState: ProcurementOneDashboardResponse = {
  kpis: [],
  message: "",
};

export function useProcurementOneDashboard() {
  return useQuery({
    queryKey: ["procurement-one", "dashboard"],
    queryFn: fetchProcurementOneDashboard,
    staleTime: 60_000,
    placeholderData: emptyState,
  });
}
