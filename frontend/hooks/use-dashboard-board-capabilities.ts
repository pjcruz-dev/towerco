"use client";

import { hasAnyPermission, permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

/**
 * Board layout powers:
 * - Everyone: rearrange widgets (personal order via DnD).
 * - Tenant / user managers: full Customize (add/remove, Layout & options, presets, publish).
 */
export function useDashboardBoardCapabilities() {
  const user = useAuthStore((state) => state.user);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const canCustomizeBoard = hasAnyPermission(
    user
      ? {
          ...user,
          permissions: effectivePermissions(),
        }
      : null,
    [permissions.tenantManage, permissions.userManage],
  );

  return {
    canCustomizeBoard,
    canRearrangeBoard: true,
  } as const;
}
