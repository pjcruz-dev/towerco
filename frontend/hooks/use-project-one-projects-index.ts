"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchProjectOneProjectsIndex } from "@/lib/api/modules/project-one-api";

const PER_PAGE = 25;

export function useProjectOneProjectsIndex(searchInput: string, sort = "updated_at:desc") {
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(searchInput, 350, () => setPage(1));

  useEffect(() => {
    setPage(1);
  }, [sort]);

  return {
    page,
    setPage,
    query: useQuery({
      queryKey: ["project-one", "projects", "index", page, PER_PAGE, debouncedSearch, sort],
      queryFn: () =>
        fetchProjectOneProjectsIndex({
          page,
          per_page: PER_PAGE,
          search: debouncedSearch.trim() || undefined,
          sort,
        }),
    }),
  };
}
