"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchProcurementOneMetadata } from "@/lib/api/modules/procurement-one-api";
import type { ProcurementPlanFeatures } from "@/modules/procurement-one/types";

export function useProcurementPlanFeatures(options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ["procurement-one", "metadata", "plan-features"],
    queryFn: fetchProcurementOneMetadata,
    staleTime: 5 * 60_000,
    enabled: options?.enabled !== false,
    select: (data) => data.plan_features,
  });
}

export type { ProcurementPlanFeatures };
