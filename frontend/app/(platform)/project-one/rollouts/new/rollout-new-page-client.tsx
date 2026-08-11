"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense } from "react";

import { AcronymText } from "@/components/help/acronym-text";
import { PermissionGate } from "@/components/layout/permission-gate";
import { RolloutCreateWizard } from "@/components/rollout/rollout-create-wizard";
import { permissions } from "@/lib/rbac/permissions";

function RolloutNewContent() {
  const searchParams = useSearchParams();
  const projectId = searchParams.get("project_id")?.trim() ?? "";

  return <RolloutCreateWizard initialProjectId={projectId} />;
}

export function RolloutNewPageClient() {
  return (
    <PermissionGate requiredPermissions={[permissions.rolloutManage]}>
      <div className="space-y-5">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">New rollout</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            <AcronymText text="Guided setup: site & MNO → program → playbook preview → create." />
          </p>
          <p className="mt-2 text-xs font-medium">
            <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/rollouts">
              Back to rollouts
            </Link>
          </p>
        </header>

        <Suspense fallback={<p className="text-sm text-muted-foreground">Loading form…</p>}>
          <RolloutNewContent />
        </Suspense>
      </div>
    </PermissionGate>
  );
}
