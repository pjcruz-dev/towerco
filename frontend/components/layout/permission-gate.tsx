"use client";

import { useRouter } from "next/navigation";
import { useEffect, useMemo } from "react";

import { hasAnyPermission, hasPermission } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

export function PermissionGate({
  requiredPermissions,
  match = "all",
  children,
  fallbackPath = "/dashboard",
}: {
  requiredPermissions: string[];
  /** `all` requires every permission; `any` requires at least one. */
  match?: "all" | "any";
  children: React.ReactNode;
  fallbackPath?: string;
}) {
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const permissionsReady = useAuthStore((state) => state.permissionsReady);

  const allowed = useMemo(() => {
    const scoped = user
      ? {
          ...user,
          permissions: effectivePermissions(),
        }
      : null;
    return match === "any"
      ? hasAnyPermission(scoped, requiredPermissions)
      : hasPermission(scoped, requiredPermissions);
  }, [effectivePermissions, match, requiredPermissions, user]);

  useEffect(() => {
    if (!permissionsReady || allowed) {
      return;
    }

    router.replace(fallbackPath);
  }, [allowed, fallbackPath, permissionsReady, router]);

  if (!permissionsReady) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        Loading…
      </div>
    );
  }

  if (!allowed) {
    return null;
  }

  return <>{children}</>;
}
