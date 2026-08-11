"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchFiberOneRoutesIndex } from "@/lib/api/modules/fiber-one-api";

const PER_PAGE = 25;

export function useFiberOneRoutesIndex(searchInput: string, sort = "name:asc") {
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(searchInput, 350, () => setPage(1));

  useEffect(() => {
    setPage(1);
  }, [sort]);

  return {
    page,
    setPage,
    query: useQuery({
      queryKey: ["fiber-one", "routes", "index", page, PER_PAGE, debouncedSearch, sort],
      queryFn: () =>
        fetchFiberOneRoutesIndex({
          page,
          per_page: PER_PAGE,
          search: debouncedSearch.trim() || undefined,
          sort,
        }),
    }),
  };
}
