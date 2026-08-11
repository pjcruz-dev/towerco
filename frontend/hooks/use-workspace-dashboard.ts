"use client";

import { useQuery } from "@tanstack/react-query";

import {
  emptyWorkspaceDashboard,
  fetchWorkspaceDashboard,
} from "@/lib/api/modules/workspace-dashboard-api";

export function useWorkspaceDashboard() {
  return useQuery({
    queryKey: ["workspace", "dashboard"],
    queryFn: fetchWorkspaceDashboard,
    staleTime: 60_000,
    placeholderData: emptyWorkspaceDashboard,
  });
}
