"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchAssetOneAssetsIndex } from "@/lib/api/modules/asset-one-api";

const PER_PAGE = 25;

export function useAssetOneAssetsIndex(searchInput: string, sort = "asset_code:asc") {
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(searchInput, 350, () => setPage(1));

  useEffect(() => {
    setPage(1);
  }, [sort]);

  return {
    page,
    setPage,
    query: useQuery({
      queryKey: ["asset-one", "assets", "index", page, PER_PAGE, debouncedSearch, sort],
      queryFn: () =>
        fetchAssetOneAssetsIndex({
          page,
          per_page: PER_PAGE,
          search: debouncedSearch.trim() || undefined,
          sort,
        }),
    }),
  };
}
