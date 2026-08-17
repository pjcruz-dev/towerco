"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState, type ReactNode } from "react";

import { AdminUserActivityTimeline } from "@/components/admin/admin-user-activity-timeline";
import { UserAuthMethodsBadges, UserMfaStatusBadge } from "@/components/admin/admin-user-security-badges";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import type { AdminUserRow } from "@/lib/api/modules/admin-users-api";
import {
  fetchAdminUserActivity,
  revokeAdminUserPasskeys,
  revokeAdminUserSessions,
} from "@/lib/api/modules/admin-users-api";
import {
  formatAuthMethods,
  formatLastActive,
  formatTimestamp,
  mfaStatusLabel,
  resolveMfaDisplayStatus,
} from "@/lib/admin/user-display";
import { useOrganizationLabel } from "@/hooks/use-organization-label";
import { getErrorMessage } from "@/lib/api/error";
import { groupPermissionsByModule, permissionLabel } from "@/lib/rbac/permission-groups";
import { getTenantRoleGuide } from "@/lib/rbac/tenant-role-guides";
import { useNotificationStore } from "@/stores/notification-store";

type TabId = "overview" | "access" | "activity";

type Props = {
  user: AdminUserRow | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onEdit: (user: AdminUserRow) => void;
  onImpersonate?: (user: AdminUserRow) => void;
  canImpersonate?: boolean;
  canManageUsers?: boolean;
};

function roleLabel(name: string): string {
  return name.replace(/_/g, " ");
}

function DetailField({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="space-y-1">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <div className="text-sm text-foreground">{children}</div>
    </div>
  );
}

export function AdminUserDetailDrawer({
  user,
  open,
  onOpenChange,
  onEdit,
  onImpersonate,
  canImpersonate = false,
  canManageUsers = false,
}: Props) {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const organizationLabel = useOrganizationLabel();
  const [tab, setTab] = useState<TabId>("overview");

  useEffect(() => {
    if (open) {
      setTab("overview");
    }
  }, [open, user?.id]);

  const activityQuery = useQuery({
    queryKey: ["admin", "users", user?.id, "activity"],
    queryFn: () => fetchAdminUserActivity(user!.id),
    enabled: open && tab === "activity" && user !== null,
  });

  const revokeMutation = useMutation({
    mutationFn: () => revokeAdminUserSessions(user!.id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "users", user?.id, "activity"] });
      void queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
      notify({ level: "success", title: "Sessions revoked", message: "All active sessions were signed out." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Revoke failed", message: getErrorMessage(error) }),
  });

  const revokePasskeysMutation = useMutation({
    mutationFn: () => revokeAdminUserPasskeys(user!.id),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "users", user?.id, "activity"] });
      notify({
        level: "success",
        title: "Passkeys revoked",
        message:
          data.revoked_count === 0
            ? "No passkeys were registered for this user."
            : `${data.revoked_count} passkey${data.revoked_count === 1 ? "" : "s"} removed.`,
      });
    },
    onError: (error) =>
      notify({ level: "error", title: "Revoke failed", message: getErrorMessage(error) }),
  });

  const permissionGroups = useMemo(
    () => groupPermissionsByModule(user?.permissions ?? []),
    [user?.permissions],
  );

  if (!user) {
    return null;
  }

  const mfaStatus = resolveMfaDisplayStatus(user.mfa_enrolled, user.mfa_required);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col p-0 sm:max-w-lg">
        <SheetHeader className="border-b border-border px-4 pb-4">
          <SheetTitle>{user.name}</SheetTitle>
          <SheetDescription>{user.email}</SheetDescription>
        </SheetHeader>

        <div className="border-b border-border px-4 py-3">
          <div className="inline-flex rounded-lg border border-border bg-muted/20 p-1">
            {(
              [
                ["overview", "Overview"],
                ["access", "Roles & access"],
                ["activity", "Activity"],
              ] as const
            ).map(([id, label]) => (
              <button
                key={id}
                type="button"
                onClick={() => setTab(id)}
                className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                  tab === id ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground"
                }`}
              >
                {label}
              </button>
            ))}
          </div>
        </div>

        <div className="flex-1 space-y-5 overflow-y-auto px-4 py-5">
          {tab === "overview" ? (
            <>
              <div className="flex flex-wrap gap-2">
                <Badge
                  variant="secondary"
                  className={
                    user.is_active
                      ? "border-success/30 bg-success/10 text-success"
                      : "text-muted-foreground"
                  }
                >
                  {user.is_active ? "Active" : "Inactive"}
                </Badge>
                <UserMfaStatusBadge mfaEnrolled={user.mfa_enrolled} mfaRequired={user.mfa_required} />
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <DetailField label="Last active">{formatLastActive(user.last_active_at)}</DetailField>
                <DetailField label="Last active (exact)">{formatTimestamp(user.last_active_at)}</DetailField>
                <DetailField label="Sign-in methods">
                  <UserAuthMethodsBadges methods={user.auth_methods} />
                </DetailField>
                <DetailField label="MFA">{mfaStatusLabel(mfaStatus)}</DetailField>
                <DetailField label="Created">{formatTimestamp(user.created_at)}</DetailField>
                <DetailField label="Deactivated">{formatTimestamp(user.deactivated_at)}</DetailField>
                <DetailField label="Job title">{user.job_title?.trim() || "—"}</DetailField>
                <DetailField label="Reports to">
                  {user.manager ? (
                    <span>
                      {user.manager.name}
                      <span className="block text-xs text-muted-foreground">{user.manager.email}</span>
                    </span>
                  ) : user.entra_manager_name || user.entra_manager_email ? (
                    <span>
                      {user.entra_manager_name ?? user.entra_manager_email}
                      <span className="block text-xs text-muted-foreground">
                        {user.entra_manager_email ?? `In Microsoft Entra — not a ${organizationLabel} user yet`}
                      </span>
                    </span>
                  ) : (
                    "—"
                  )}
                </DetailField>
                <DetailField label="Direct reports">{user.direct_report_count ?? 0}</DetailField>
              </div>

              <DetailField label="Auth methods summary">{formatAuthMethods(user.auth_methods)}</DetailField>
            </>
          ) : null}

          {tab === "access" ? (
            <>
              <section className="space-y-2">
                <h3 className="text-sm font-medium text-foreground">Assigned roles</h3>
                <div className="flex flex-wrap gap-1.5">
                  {user.roles.map((role) => (
                    <Badge key={role} variant="secondary">
                      {roleLabel(role)}
                    </Badge>
                  ))}
                </div>
                {user.roles.map((role) => {
                  const guide = getTenantRoleGuide(role);
                  if (!guide) {
                    return null;
                  }

                  return (
                    <p key={`${role}-guide`} className="text-xs text-muted-foreground">
                      <span className="font-medium text-foreground">{roleLabel(role)}:</span> {guide.summary}
                    </p>
                  );
                })}
              </section>

              <section className="space-y-3">
                <div>
                  <h3 className="text-sm font-medium text-foreground">Effective permissions</h3>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    Resolved from assigned roles. {user.permissions.length} permission
                    {user.permissions.length === 1 ? "" : "s"} total.
                  </p>
                </div>

                {permissionGroups.length === 0 ? (
                  <p className="text-sm text-muted-foreground">No permissions resolved for this user.</p>
                ) : (
                  permissionGroups.map((group) => (
                    <div key={group.id} className="rounded-lg border border-border/60 bg-muted/10 p-3">
                      <p className="text-xs font-medium text-muted-foreground">{group.label}</p>
                      <div className="mt-2 flex flex-wrap gap-1">
                        {group.permissions.map((permission) => (
                          <Badge key={permission} variant="ghost" className="font-normal">
                            {permissionLabel(permission)}
                          </Badge>
                        ))}
                      </div>
                    </div>
                  ))
                )}
              </section>
            </>
          ) : null}

          {tab === "activity" ? (
            <>
              {canManageUsers ? (
                <div className="space-y-3">
                  <div className="flex items-center justify-between gap-3 rounded-lg border border-border bg-muted/20 px-3 py-3">
                    <div>
                      <p className="text-sm font-medium text-foreground">Revoke all sessions</p>
                      <p className="text-xs text-muted-foreground">
                        Signs this user out on every device immediately.
                      </p>
                    </div>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={revokeMutation.isPending}
                      onClick={() => revokeMutation.mutate()}
                    >
                      {revokeMutation.isPending ? "Revoking…" : "Revoke all"}
                    </Button>
                  </div>
                  <div className="flex items-center justify-between gap-3 rounded-lg border border-border bg-muted/20 px-3 py-3">
                    <div>
                      <p className="text-sm font-medium text-foreground">Revoke all passkeys</p>
                      <p className="text-xs text-muted-foreground">
                        Removes enrolled fingerprint / Windows Hello credentials. User can still sign in
                        with password or Microsoft.
                      </p>
                    </div>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={revokePasskeysMutation.isPending}
                      onClick={() => {
                        if (
                          window.confirm(
                            `Revoke all passkeys for ${user.email}? They will need password or Microsoft sign-in until they enroll again.`,
                          )
                        ) {
                          revokePasskeysMutation.mutate();
                        }
                      }}
                    >
                      {revokePasskeysMutation.isPending ? "Revoking…" : "Revoke passkeys"}
                    </Button>
                  </div>
                </div>
              ) : null}

              <AdminUserActivityTimeline
                entries={activityQuery.data ?? []}
                isLoading={activityQuery.isLoading}
              />
            </>
          ) : null}
        </div>

        <SheetFooter className="border-t border-border px-4 py-4 sm:flex-row sm:justify-end">
          {canImpersonate && user.can_impersonate && onImpersonate ? (
            <Button variant="outline" onClick={() => onImpersonate(user)}>
              View as user
            </Button>
          ) : null}
          <Button
            onClick={() => {
              onOpenChange(false);
              onEdit(user);
            }}
          >
            Edit user
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
