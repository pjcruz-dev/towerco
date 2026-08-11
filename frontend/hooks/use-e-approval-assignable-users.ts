"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchEApprovalAssignableUsers } from "@/lib/api/modules/e-approval-api";
import type { EApprovalAssignableUser } from "@/modules/e-approval/types";

export const eApprovalAssignableUsersQueryKey = ["e-approval", "assignable-users"] as const;

export function useEApprovalAssignableUsers(enabled = true) {
  return useQuery({
    queryKey: eApprovalAssignableUsersQueryKey,
    queryFn: fetchEApprovalAssignableUsers,
    enabled,
    staleTime: 120_000,
  });
}

export function mapEApprovalAssignableUsersToOptions(
  users: EApprovalAssignableUser[] | undefined,
): { id: string; label: string }[] {
  return (users ?? []).map((user) => ({
    id: user.id,
    label: `${user.name} (${user.email})`,
  }));
}
