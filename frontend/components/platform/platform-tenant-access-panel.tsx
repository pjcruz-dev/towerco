"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { ExternalLink, UserRound } from "lucide-react";
import { useMemo, useState } from "react";

import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformListTenantUsers,
  platformStartTenantImpersonation,
  type PlatformTenantRow,
} from "@/lib/api/modules/platform-api";
import { openPlatformImpersonationSession } from "@/lib/auth/platform-impersonation-handoff";
import { tenantLoginUrl } from "@/lib/tenant/resolve-tenant-domain";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  tenant: PlatformTenantRow;
  onEditBranding: () => void;
};

export function PlatformTenantAccessPanel({ tenant, onEditBranding }: Props) {
  const notify = useNotificationStore((s) => s.push);
  const accessToken = usePlatformAuthStore((s) => s.accessToken);
  const isHydrated = usePlatformAuthStore((s) => s.isHydrated);

  const primaryDomain = tenant.domains[0] ?? null;
  const loginUrl = primaryDomain ? tenantLoginUrl(primaryDomain) : null;

  const [userId, setUserId] = useState("");
  const [reason, setReason] = useState("");

  const usersQuery = useQuery({
    queryKey: ["platform", "tenants", tenant.id, "users"],
    queryFn: () => platformListTenantUsers(tenant.id),
    enabled: Boolean(isHydrated && accessToken),
  });

  const users = usersQuery.data ?? [];
  const selectedUser = useMemo(
    () => users.find((row) => row.id === userId) ?? null,
    [userId, users],
  );

  const impersonateMutation = useMutation({
    mutationFn: () =>
      platformStartTenantImpersonation(tenant.id, {
        user_id: userId,
        reason: reason.trim(),
      }),
    onSuccess: (session) => {
      const domain = session.tenant_domain ?? primaryDomain;
      if (!domain) {
        notify({
          level: "error",
          title: "Impersonation started",
          message: "No tenant domain available to open the workspace.",
        });
        return;
      }

      openPlatformImpersonationSession(domain, session);
      notify({
        level: "success",
        title: "Impersonation started",
        message: `Opened ${selectedUser?.email ?? "tenant user"} in a new tab.`,
      });
      setReason("");
    },
    onError: (error) => {
      notify({
        level: "error",
        title: "Impersonation failed",
        message: getErrorMessage(error),
      });
    },
  });

  const canSubmit =
    userId !== "" && reason.trim().length >= 3 && !impersonateMutation.isPending;

  return (
    <Card className="rounded-xl shadow-sm">
      <CardContent className="space-y-6 p-6 text-sm">
        <div className="space-y-2">
          <h3 className="text-base font-medium text-foreground">Tenant workspace</h3>
          {loginUrl ? (
            <a href={loginUrl} target="_blank" rel="noopener noreferrer" className={buttonVariants()}>
              <ExternalLink className="size-4" />
              Open tenant workspace
            </a>
          ) : (
            <p className="text-muted-foreground">No primary domain configured.</p>
          )}
        </div>

        <div className="space-y-3 border-t border-border pt-6">
          <div className="flex items-center gap-2">
            <UserRound className="size-4 text-primary" />
            <h3 className="text-base font-medium text-foreground">Platform impersonation</h3>
          </div>
          <p className="text-muted-foreground">
            View the tenant as a specific user for support. Sessions are time-limited and recorded in
            the activity log. Tenant administrators can also be impersonated from here.
          </p>

          {usersQuery.isLoading ? (
            <p className="text-muted-foreground">Loading users…</p>
          ) : users.length === 0 ? (
            <p className="text-muted-foreground">No active users found in this tenant.</p>
          ) : (
            <div className="grid gap-4 sm:max-w-lg">
              <div className="space-y-2">
                <Label htmlFor="impersonate-user">User</Label>
                <Select
                  id="impersonate-user"
                  value={userId}
                  onChange={(event) => setUserId(event.target.value)}
                  aria-label="Select a user to impersonate"
                >
                  <option value="">Select a user</option>
                  {users.map((row) => (
                    <option key={row.id} value={row.id}>
                      {row.name} ({row.email})
                      {row.roles.length > 0 ? ` · ${row.roles.join(", ")}` : ""}
                    </option>
                  ))}
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="impersonate-reason">Reason</Label>
                <textarea
                  id="impersonate-reason"
                  value={reason}
                  onChange={(event) => setReason(event.target.value)}
                  placeholder="Support ticket, workflow debugging, etc."
                  rows={3}
                  className={cn(
                    "flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm",
                    "ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none",
                    "focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
                  )}
                />
              </div>

              <Button
                type="button"
                disabled={!canSubmit}
                onClick={() => impersonateMutation.mutate()}
              >
                {impersonateMutation.isPending ? "Starting…" : "View as user"}
              </Button>
            </div>
          )}
        </div>

        <div className="border-t border-border pt-4">
          <Button type="button" variant="outline" onClick={onEditBranding}>
            Edit branding
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
