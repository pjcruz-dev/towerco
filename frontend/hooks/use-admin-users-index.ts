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

export type AdminUsersIndexFilters = {
  status: AdminUserStatusFilter;
  lastActive: AdminUserLastActiveFilter;
  mfa: AdminUserMfaFilter;
  role: string;
  department: string;
  managerId: string;
  license: string;
};

export function useAdminUsersIndex(searchInput: string, filters: AdminUsersIndexFilters, sort = "name:asc") {
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(searchInput, 350, () => setPage(1));
  const { status, lastActive, mfa, role, department, managerId, license } = filters;

  useEffect(() => {
    setPage(1);
  }, [sort, status, lastActive, mfa, role, department, managerId, license]);

  return {
    page,
    setPage,
    debouncedSearch,
    query: useQuery({
      queryKey: [
        "admin",
        "users",
        page,
        PER_PAGE,
        debouncedSearch,
        status,
        lastActive,
        mfa,
        role,
        department,
        managerId,
        license,
        sort,
      ],
      queryFn: () =>
        fetchAdminUsersIndex({
          page,
          per_page: PER_PAGE,
          search: debouncedSearch.trim() || undefined,
          status,
          last_active: lastActive,
          mfa,
          role: role && role !== "all" ? role : undefined,
          department: department && department !== "all" ? department : undefined,
          manager_id: managerId && managerId !== "all" ? managerId : undefined,
          license: license && license !== "all" ? license : undefined,
          sort,
        }),
      staleTime: USERS_STALE_MS,
      placeholderData: keepPreviousData,
    }),
  };
}
