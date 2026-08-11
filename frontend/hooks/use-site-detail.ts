"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchSite } from "@/lib/api/modules/sites-api";

export function useSiteDetail(siteId: string) {
  return useQuery({
    queryKey: ["sites", "detail", siteId],
    queryFn: () => fetchSite(siteId),
    enabled: Boolean(siteId),
  });
}
