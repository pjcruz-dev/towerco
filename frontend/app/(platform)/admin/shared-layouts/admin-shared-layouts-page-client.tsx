"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { LayoutTemplate, Trash2 } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { PageLoadingShell } from "@/components/ui/page-skeletons";
import { getErrorMessage } from "@/lib/api/error";
import {
  deleteSharedUserUiPreference,
  fetchSharedUiLayoutAudit,
} from "@/lib/api/modules/user-ui-preferences-api";
import { permissions } from "@/lib/rbac/permissions";
import { labelSharedUiLayoutKey } from "@/lib/ui/shared-ui-layout-labels";
import { useNotificationStore } from "@/stores/notification-store";

function formatWhen(value: string | null | undefined): string {
  if (!value) return "—";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

export function AdminSharedLayoutsPageClient() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);

  const auditQuery = useQuery({
    queryKey: ["admin", "shared-ui-layouts"],
    queryFn: fetchSharedUiLayoutAudit,
  });

  const clearMutation = useMutation({
    mutationFn: (key: string) => deleteSharedUserUiPreference(key),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["admin", "shared-ui-layouts"] });
      notify({
        level: "success",
        title: "Tenant default cleared",
        message: "Users without a personal override fall back to page defaults.",
      });
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not clear layout", message: getErrorMessage(error) });
    },
  });

  const items = auditQuery.data ?? [];

  return (
    <PermissionGate
      requiredPermissions={[permissions.tenantManage, permissions.userManage]}
      match="any"
    >
      <div className="flex flex-col gap-6 p-6">
        <header>
          <h1 className="text-2xl font-semibold text-foreground">Shared layouts</h1>
          <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
            Tenant defaults published from Customize (page boards) or column layout menus. Clearing a
            row removes the tenant default; personal overrides are unchanged.
          </p>
        </header>

        <div className="grid gap-3 sm:grid-cols-3">
          <Card className="rounded-xl shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Published</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums text-foreground">
              {auditQuery.isLoading ? "—" : items.length}
            </CardContent>
          </Card>
          <Card className="rounded-xl shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Page boards</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums text-foreground">
              {auditQuery.isLoading
                ? "—"
                : items.filter((row) => row.kind === "dashboard-layout").length}
            </CardContent>
          </Card>
          <Card className="rounded-xl shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">Column layouts</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums text-foreground">
              {auditQuery.isLoading
                ? "—"
                : items.filter((row) => row.kind === "module-list").length}
            </CardContent>
          </Card>
        </div>

        {auditQuery.isLoading ? <PageLoadingShell label="Loading shared layouts" /> : null}

        {auditQuery.isError ? (
          <p className="text-sm text-destructive">{getErrorMessage(auditQuery.error)}</p>
        ) : null}

        {!auditQuery.isLoading && items.length === 0 ? (
          <div className="rounded-xl border border-dashed border-border bg-card px-6 py-10 text-center shadow-sm">
            <LayoutTemplate className="mx-auto size-8 text-muted-foreground" aria-hidden />
            <p className="mt-3 text-sm font-medium text-foreground">No tenant defaults yet</p>
            <p className="mt-1 text-sm text-muted-foreground">
              Publish from Customize → Layout preset → Publish as tenant default.
            </p>
          </div>
        ) : null}

        {!auditQuery.isLoading && items.length > 0 ? (
          <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground">
                <tr>
                  <th className="px-4 py-3">Surface</th>
                  <th className="px-4 py-3">Type</th>
                  <th className="px-4 py-3">Summary</th>
                  <th className="px-4 py-3">Updated by</th>
                  <th className="px-4 py-3">Updated</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {items.map((row) => (
                  <tr key={row.key} className="align-top">
                    <td className="px-4 py-3">
                      <p className="font-medium text-foreground">{labelSharedUiLayoutKey(row.key)}</p>
                      <p className="mt-0.5 font-mono text-[11px] text-muted-foreground">{row.key}</p>
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">
                      {row.kind === "dashboard-layout" ? "Page board" : "Columns"}
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">{row.summary}</td>
                    <td className="px-4 py-3 text-muted-foreground">{row.updated_by_name ?? "—"}</td>
                    <td className="px-4 py-3 text-muted-foreground">{formatWhen(row.updated_at)}</td>
                    <td className="px-4 py-3 text-right">
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="gap-1.5"
                        disabled={clearMutation.isPending}
                        onClick={() => {
                          if (
                            window.confirm(
                              `Clear tenant default for “${labelSharedUiLayoutKey(row.key)}”? Users keep personal layouts.`,
                            )
                          ) {
                            clearMutation.mutate(row.key);
                          }
                        }}
                      >
                        <Trash2 className="size-3.5" aria-hidden />
                        Clear
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </div>
    </PermissionGate>
  );
}
