"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { useDebouncedValue } from "@/hooks/use-debounced-value";
import {
  fetchAdminUsersIndex,
  type AdminUserLastActiveFilter,
  type AdminUserMfaFilter,
  type AdminUserStatusFilter,
} from "@/lib/api/modules/admin-users-api";

const PER_PAGE = 25;
const USERS_STALE_MS = 30_000;

export function useAdminUsersIndex(
  searchInput: string,
  status: AdminUserStatusFilter,
  lastActive: AdminUserLastActiveFilter,
  mfa: AdminUserMfaFilter,
  role: string,
  sort = "name:asc",
) {
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(searchInput, 350, () => setPage(1));

  useEffect(() => {
    setPage(1);
  }, [sort]);

  return {
    page,
    setPage,
    debouncedSearch,
    query: useQuery({
      queryKey: ["admin", "users", page, PER_PAGE, debouncedSearch, status, lastActive, mfa, role, sort],
      queryFn: () =>
        fetchAdminUsersIndex({
          page,
          per_page: PER_PAGE,
          search: debouncedSearch.trim() || undefined,
          status,
          last_active: lastActive,
          mfa,
          role: role || undefined,
          sort,
        }),
      staleTime: USERS_STALE_MS,
      placeholderData: keepPreviousData,
    }),
  };
}
