"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchTowerOneTowersIndex } from "@/lib/api/modules/tower-one-api";

const PER_PAGE = 25;

export function useTowerOneTowersIndex(searchInput: string, sort = "updated_at:desc") {
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(searchInput, 350, () => setPage(1));

  useEffect(() => {
    setPage(1);
  }, [sort]);

  return {
    page,
    setPage,
    query: useQuery({
      queryKey: ["tower-one", "towers", "index", page, PER_PAGE, debouncedSearch, sort],
      queryFn: () =>
        fetchTowerOneTowersIndex({
          page,
          per_page: PER_PAGE,
          search: debouncedSearch.trim() || undefined,
          sort,
        }),
    }),
  };
}
