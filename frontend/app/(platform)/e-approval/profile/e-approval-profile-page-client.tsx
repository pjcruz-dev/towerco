"use client";

import Link from "next/link";
import { FileStack, Inbox, Users } from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { EApprovalDelegationPanel } from "@/components/e-approval/e-approval-delegation-panel";
import { EApprovalMeSignaturePanel } from "@/components/e-approval/e-approval-me-signature-panel";
import { EApprovalBackLink, EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { PermissionGate } from "@/components/layout/permission-gate";
import { fetchEApprovalSettingsPublic } from "@/lib/api/modules/e-approval-api";
import {
  mapEApprovalAssignableUsersToOptions,
  useEApprovalAssignableUsers,
} from "@/hooks/use-e-approval-assignable-users";
import { usePermission } from "@/hooks/use-permission";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

export function EApprovalProfilePageClient() {
  const canDelegate = usePermission([permissions.eApprovalApprove]);

  const publicSettingsQuery = useQuery({
    queryKey: ["e-approval", "settings", "public"],
    queryFn: fetchEApprovalSettingsPublic,
    enabled: canDelegate,
  });

  const delegationUi = publicSettingsQuery.data?.feature_delegation_ui === "true";
  const usersQuery = useEApprovalAssignableUsers(canDelegate && delegationUi);
  const approverOptions = mapEApprovalAssignableUsersToOptions(usersQuery.data);

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalView]}>
      <div className="space-y-6">
        <EApprovalPageHeader
          title="My E-Approval profile"
          description={
            <>
              <EApprovalBackLink href="/e-approval">Dashboard</EApprovalBackLink>
              {" · "}Signature and delegation settings used on approvals and printed documents.
            </>
          }
        />

        <div className="grid gap-3 sm:grid-cols-2">
          {canDelegate ? (
            <Link
              href="/e-approval/approvals?awaiting_me=1"
              className={cn(
                "flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm transition-colors",
                "hover:border-primary/30 hover:bg-muted/30",
              )}
            >
              <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Inbox className="h-4 w-4" />
              </div>
              <div>
                <p className="text-sm font-medium">Approval inbox</p>
                <p className="text-xs text-muted-foreground">Open items awaiting you</p>
              </div>
            </Link>
          ) : (
            <Link
              href="/e-approval/submissions"
              className={cn(
                "flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm transition-colors",
                "hover:border-primary/30 hover:bg-muted/30",
              )}
            >
              <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Inbox className="h-4 w-4" />
              </div>
              <div>
                <p className="text-sm font-medium">My submissions</p>
                <p className="text-xs text-muted-foreground">Track your requests</p>
              </div>
            </Link>
          )}
          <Link
            href="/e-approval/submissions/new"
            className={cn(
              "flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm transition-colors",
              "hover:border-primary/30 hover:bg-muted/30",
            )}
          >
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
              <FileStack className="h-4 w-4" />
            </div>
            <div>
              <p className="text-sm font-medium">New submission</p>
              <p className="text-xs text-muted-foreground">Start a new request</p>
            </div>
          </Link>
        </div>

        <EApprovalMeSignaturePanel />

        {canDelegate && delegationUi ? (
          <EApprovalSectionCard
            title="Out-of-office delegation"
            description="Temporarily assign your approval steps to another user."
            bodyClassName="p-4"
          >
            <div className="mb-3 flex items-center gap-2 text-xs font-medium text-muted-foreground">
              <Users className="h-3.5 w-3.5" aria-hidden />
              Active delegations
            </div>
            <EApprovalDelegationPanel approverOptions={approverOptions} />
          </EApprovalSectionCard>
        ) : canDelegate ? (
          <p className="rounded-xl border border-border bg-card px-4 py-3 text-sm text-muted-foreground shadow-sm">
            Out-of-office delegation is disabled for this tenant. An administrator can enable it under{" "}
            <Link href="/e-approval/settings" className="text-primary hover:underline">
              module settings
            </Link>
            .
          </p>
        ) : null}
      </div>
    </PermissionGate>
  );
}
