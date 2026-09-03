"use client";

import { useMemo } from "react";

import { hasPermission } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

export function usePermission(requiredPermissions: string[] = []) {
  const user = useAuthStore((state) => state.user);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const requiredKey = requiredPermissions.join("\0");

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
    // requiredKey captures the permission list without depending on a new array identity each render.
    // eslint-disable-next-line react-hooks/exhaustive-deps -- requiredPermissions read via requiredKey
    [effectivePermissions, requiredKey, user],
  );
}
