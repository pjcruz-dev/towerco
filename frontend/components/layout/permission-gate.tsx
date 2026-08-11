"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { usePermission } from "@/hooks/use-permission";
import { useAuthStore } from "@/stores/auth-store";

export function PermissionGate({
  requiredPermissions,
  children,
  fallbackPath = "/dashboard",
}: {
  requiredPermissions: string[];
  children: React.ReactNode;
  fallbackPath?: string;
}) {
  const router = useRouter();
  const allowed = usePermission(requiredPermissions);
  const permissionsReady = useAuthStore((state) => state.permissionsReady);

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
