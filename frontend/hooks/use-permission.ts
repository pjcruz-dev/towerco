"use client";

import { useMemo } from "react";

import { hasPermission } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

export function usePermission(requiredPermissions: string[] = []) {
  const user = useAuthStore((state) => state.user);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);

  return useMemo(
    () =>
      hasPermission(
        user
          ? {
              ...user,
              permissions: effectivePermissions(),
            }
          : null,
        requiredPermissions,
      ),
    [effectivePermissions, requiredPermissions, user],
  );
}
