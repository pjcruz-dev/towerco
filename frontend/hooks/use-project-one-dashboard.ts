"use client";

import { useQuery } from "@tanstack/react-query";

import {
  fetchProjectOneDashboard,
  fetchProjectOneDashboardMap,
} from "@/lib/api/modules/project-one-api";
import type { ProjectOneDashboardResponse } from "@/modules/project-one/types";

const emptyState: ProjectOneDashboardResponse = {
  kpis: [],
  sites: [],
  approvals: [],
  milestones: [],
  actions: [],
};

export function useProjectOneDashboard() {
  const dashboardQuery = useQuery({
    queryKey: ["project-one", "dashboard"],
    queryFn: () => fetchProjectOneDashboard(),
    staleTime: 60_000,
    placeholderData: emptyState,
  });

  const mapQuery = useQuery({
    queryKey: ["project-one", "dashboard", "map"],
    queryFn: fetchProjectOneDashboardMap,
    staleTime: 45_000,
  });

  const dashboard = dashboardQuery.data ?? emptyState;

  const data: ProjectOneDashboardResponse = {
    ...dashboard,
    map_pins: mapQuery.isSuccess ? mapQuery.data : undefined,
  };

  return {
    data,
    isFetching: dashboardQuery.isFetching,
    isPlaceholderData: dashboardQuery.isPlaceholderData,
    isMapLoading: !mapQuery.isSuccess && !mapQuery.isError,
    isError: dashboardQuery.isError,
    isMapError: mapQuery.isError,
    refetch: () => {
      void dashboardQuery.refetch();
      void mapQuery.refetch();
    },
  };
}
