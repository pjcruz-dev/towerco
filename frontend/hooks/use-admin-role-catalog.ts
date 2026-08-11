"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchAdminRoleCatalog } from "@/lib/api/modules/admin-roles-api";

const ROLE_CATALOG_STALE_MS = 5 * 60_000;

export function useAdminRoleCatalog(enabled = true) {
  return useQuery({
    queryKey: ["admin", "roles"],
    queryFn: fetchAdminRoleCatalog,
    enabled,
    staleTime: ROLE_CATALOG_STALE_MS,
  });
}
