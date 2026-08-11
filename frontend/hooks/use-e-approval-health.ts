"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchEApprovalHealth } from "@/lib/api/modules/e-approval-api";

export function useEApprovalHealth() {
  return useQuery({
    queryKey: ["e-approval", "health"],
    queryFn: fetchEApprovalHealth,
    staleTime: 120_000,
  });
}
