"use client";

import { useQuery } from "@tanstack/react-query";
import { useState } from "react";

import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchProjectOneApprovalsIndex } from "@/lib/api/modules/project-one-api";

const PER_PAGE = 25;

export type ApprovalListStatus = "pending" | "approved" | "rejected" | "all";

export function useProjectOneApprovalsIndex(
  searchInput: string,
  status: ApprovalListStatus,
  sort: string,
) {
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(searchInput, 350, () => setPage(1));

  return {
    page,
    setPage,
    query: useQuery({
      queryKey: ["project-one", "approvals", "index", status, page, PER_PAGE, debouncedSearch, sort],
      queryFn: () =>
        fetchProjectOneApprovalsIndex({
          page,
          per_page: PER_PAGE,
          status,
          search: debouncedSearch.trim() || undefined,
          sort,
        }),
    }),
  };
}
