"use client";

import { useQuery } from "@tanstack/react-query";
import { useState } from "react";

import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchRolloutsIndex } from "@/lib/api/modules/rollout-api";
import type { RolloutIndexParams } from "@/modules/rollout/types";

const PER_PAGE = 25;

export type RolloutIndexFilters = {
  status: string;
  mno: string;
  project_type: string;
  region: string;
  sort: string;
  sla_at_risk: boolean;
};

const DEFAULT_FILTERS: RolloutIndexFilters = {
  status: "all",
  mno: "all",
  project_type: "all",
  region: "all",
  sort: "created_at:desc",
  sla_at_risk: false,
};

export function useRolloutsIndex(searchInput: string, filters: RolloutIndexFilters = DEFAULT_FILTERS) {
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(searchInput, 350, () => setPage(1));

  const params: RolloutIndexParams = {
    page,
    per_page: PER_PAGE,
    search: debouncedSearch.trim() || undefined,
    sort: filters.sort,
    status: filters.status !== "all" ? filters.status : undefined,
    mno: filters.mno !== "all" ? filters.mno : undefined,
    project_type: filters.project_type !== "all" ? filters.project_type : undefined,
    region: filters.region !== "all" ? filters.region : undefined,
    sla_at_risk: filters.sla_at_risk ? true : undefined,
  };

  return {
    page,
    setPage,
    params,
    query: useQuery({
      queryKey: ["project-one", "rollouts", "index", params],
      queryFn: () => fetchRolloutsIndex(params),
    }),
  };
}

export { DEFAULT_FILTERS, PER_PAGE };
