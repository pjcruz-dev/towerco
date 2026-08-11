"use client";

import Link from "next/link";

import { AcronymText } from "@/components/help/acronym-text";
import { PermissionGate } from "@/components/layout/permission-gate";
import { RolloutPublicHolidaysPanel } from "@/components/rollout/rollout-public-holidays-panel";
import { usePermission } from "@/hooks/use-permission";
import { permissions } from "@/lib/rbac/permissions";

export function PublicHolidaysPageClient() {
  const canConfigure = usePermission([permissions.playbookConfigure, permissions.tenantManage]);

  return (
    <PermissionGate requiredPermissions={[permissions.projectOneView]}>
      <div className="space-y-5">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Public holidays</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            <AcronymText text="Working-day exclusions for rollout SLA calculations. Weekends are always non-working." />
          </p>
          <p className="mt-2 text-xs font-medium">
            <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/rollout-playbook">
              Rollout playbook settings
            </Link>
            {" · "}
            <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/rollouts">
              Rollouts
            </Link>
          </p>
        </header>

        <RolloutPublicHolidaysPanel canConfigure={canConfigure} />
      </div>
    </PermissionGate>
  );
}
