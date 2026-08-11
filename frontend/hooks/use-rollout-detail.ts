"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchRolloutDetail } from "@/lib/api/modules/rollout-api";

export function useRolloutDetail(id: string) {
  return useQuery({
    queryKey: ["project-one", "rollouts", "detail", id],
    queryFn: () => fetchRolloutDetail(id),
    enabled: Boolean(id),
  });
}
