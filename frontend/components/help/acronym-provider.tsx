"use client";

import { useQuery } from "@tanstack/react-query";
import { createContext, useContext, useMemo, type ReactNode } from "react";

import { apiClient } from "@/lib/api/client";
import { buildAcronymMap } from "@/lib/operational-acronyms/build-acronym-map";
import { DEFAULT_OPERATIONAL_ACRONYMS } from "@/lib/operational-acronyms/defaults";
import type { OperationalAcronym, OperationalAcronymMap } from "@/lib/operational-acronyms/types";

type AcronymContextValue = {
  map: OperationalAcronymMap;
  isLoading: boolean;
};

const AcronymContext = createContext<AcronymContextValue>({
  map: {},
  isLoading: false,
});

async function fetchPublicOperationalAcronyms(): Promise<OperationalAcronym[]> {
  const response = await apiClient.get<{ data: OperationalAcronym[] }>("/public/operational-acronyms");
  return response.data.data ?? [];
}

export function AcronymProvider({ children }: { children: ReactNode }) {
  const query = useQuery({
    queryKey: ["operational-acronyms", "public"],
    queryFn: fetchPublicOperationalAcronyms,
    staleTime: 1000 * 60 * 10,
    retry: 1,
  });

  const map = useMemo(() => {
    if (query.data && query.data.length > 0) {
      return buildAcronymMap(query.data);
    }

    return buildAcronymMap(
      DEFAULT_OPERATIONAL_ACRONYMS.map((row, index) => ({
        id: `default-${index}`,
        acronym: row.acronym,
        definition: row.definition,
        category: row.category ?? null,
        sort_order: index,
        is_active: true,
      })),
    );
  }, [query.data]);

  const value = useMemo(
    () => ({
      map,
      isLoading: query.isLoading,
    }),
    [map, query.isLoading],
  );

  return <AcronymContext.Provider value={value}>{children}</AcronymContext.Provider>;
}

export function useOperationalAcronyms(): AcronymContextValue {
  return useContext(AcronymContext);
}
