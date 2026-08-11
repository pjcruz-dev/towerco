"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchTowerOneTower } from "@/lib/api/modules/tower-one-api";

export function useTowerOneTowerDetail(towerId: string) {
  return useQuery({
    queryKey: ["tower-one", "towers", "detail", towerId],
    queryFn: () => fetchTowerOneTower(towerId),
    enabled: Boolean(towerId),
  });
}
