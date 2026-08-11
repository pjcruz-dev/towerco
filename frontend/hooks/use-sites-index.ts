"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchSitesIndex } from "@/lib/api/modules/sites-api";

const PER_PAGE = 25;

export function useSitesIndex(searchInput: string, sort = "site_code:asc") {
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(searchInput, 350, () => setPage(1));

  useEffect(() => {
    setPage(1);
  }, [sort]);

  return {
    page,
    setPage,
    query: useQuery({
      queryKey: ["sites", "index", page, PER_PAGE, debouncedSearch, sort],
      queryFn: () =>
        fetchSitesIndex({
          page,
          per_page: PER_PAGE,
          search: debouncedSearch.trim() || undefined,
          sort,
        }),
    }),
  };
}
