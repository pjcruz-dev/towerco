"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchRolloutPlaybookStatus } from "@/lib/api/modules/rollout-api";

export function useRolloutPlaybookStatus() {
  return useQuery({
    queryKey: ["project-one", "rollout-playbook"],
    queryFn: fetchRolloutPlaybookStatus,
  });
}
