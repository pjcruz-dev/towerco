"use client";

import Link from "next/link";

import { PermissionGate } from "@/components/layout/permission-gate";
import { RolloutGeographyPanel } from "@/components/rollout/rollout-geography-panel";
import { usePermission } from "@/hooks/use-permission";
import { permissions } from "@/lib/rbac/permissions";

export function GeographyPageClient() {
  const canConfigure = usePermission([permissions.playbookConfigure, permissions.tenantManage]);

  return (
    <PermissionGate requiredPermissions={[permissions.projectOneView]}>
      <div className="space-y-5">
        <header className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Geography lookups</h1>
          <p className="text-sm text-muted-foreground">
            Manage PSA region codes and telecom territories for Project One site profiles.
          </p>
          <p className="pt-1 text-xs text-muted-foreground">
            <Link className="font-medium text-foreground underline-offset-4 hover:underline" href="/project-one/public-holidays">
              Public holidays
            </Link>
            <span className="mx-1.5 text-border">·</span>
            <Link className="font-medium text-foreground underline-offset-4 hover:underline" href="/project-one/rollouts/new">
              New rollout
            </Link>
          </p>
        </header>

        <RolloutGeographyPanel canConfigure={canConfigure} />
      </div>
    </PermissionGate>
  );
}
