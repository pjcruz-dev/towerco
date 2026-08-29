"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { Download, HardDrive } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { getErrorMessage } from "@/lib/api/error";
import { downloadTenantBackup, fetchTenantBackups } from "@/lib/api/modules/tenant-backups-api";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

function formatBytes(bytes: number | null | undefined): string {
  if (bytes == null || bytes <= 0) return "—";
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatWhen(value: string | null | undefined): string {
  if (!value) return "—";
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

export function AdminBackupsPageClient() {
  const notify = useNotificationStore((s) => s.push);
  const user = useAuthStore((s) => s.user);
  const effectivePermissions = useAuthStore((s) => s.effectivePermissions);
  const scopedUser = user ? { ...user, permissions: effectivePermissions() } : null;
  const canManage = hasPermission(scopedUser, [permissions.tenantManage]);

  const backupsQuery = useQuery({
    queryKey: ["admin", "backups"],
    queryFn: fetchTenantBackups,
    enabled: canManage,
  });

  const downloadMutation = useMutation({
    mutationFn: (row: { id: string; name: string }) => downloadTenantBackup(row.id, row.name),
    onSuccess: () => {
      notify({ level: "success", title: "Download started", message: "Backup file saved to your device." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Download failed", message: getErrorMessage(error) });
    },
  });

  if (!canManage) {
    return (
      <div className="p-6">
        <p className="text-sm text-muted-foreground">You need tenant administrator access to download backups.</p>
      </div>
    );
  }

  const meta = backupsQuery.data?.meta;
  const rows = backupsQuery.data?.data ?? [];

  return (
    <div className="flex flex-col gap-6 p-6">
      <header>
        <h1 className="text-2xl font-semibold text-foreground">Backups</h1>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Download completed database backups for this organization. Create and restore are managed by
          TowerOS platform operators.
        </p>
      </header>

      <div className="rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-700/50 dark:bg-amber-950/30 dark:text-amber-100">
        Backups older than {meta?.retention_days ?? 15} days are removed automatically. Download a local
        copy if you need longer retention. Restores can only be performed by platform support.
      </div>

      <div className="grid gap-3 sm:grid-cols-3">
        <Card className="rounded-xl shadow-sm">
          <CardContent className="flex items-center gap-3 p-4">
            <HardDrive className="size-5 text-muted-foreground" />
            <div>
              <p className="text-xs text-muted-foreground">Completed</p>
              <p className="text-lg font-semibold">{meta?.completed ?? 0}</p>
            </div>
          </CardContent>
        </Card>
        <Card className="rounded-xl shadow-sm">
          <CardContent className="p-4">
            <p className="text-xs text-muted-foreground">Latest</p>
            <p className="text-sm font-medium">
              {meta?.latest_at
                ? new Date(meta.latest_at).toLocaleDateString(undefined, {
                    month: "short",
                    day: "numeric",
                    year: "numeric",
                  })
                : "—"}
            </p>
          </CardContent>
        </Card>
        <Card className="rounded-xl shadow-sm">
          <CardContent className="p-4">
            <p className="text-xs text-muted-foreground">Storage used</p>
            <p className="text-sm font-medium">{formatBytes(meta?.storage_bytes)}</p>
          </CardContent>
        </Card>
      </div>

      <Card className="rounded-xl shadow-sm">
        <CardHeader>
          <CardTitle className="text-base font-medium">Available backups</CardTitle>
        </CardHeader>
        <CardContent>
          {backupsQuery.isLoading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">No completed backups are available yet.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[520px] text-left text-sm">
                <thead className="border-b border-border text-xs text-muted-foreground">
                  <tr>
                    <th className="px-2 py-2 font-medium">Name</th>
                    <th className="px-2 py-2 font-medium">Size</th>
                    <th className="px-2 py-2 font-medium">Created</th>
                    <th className="px-2 py-2 font-medium text-right">Download</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.id} className="border-b border-border/70 last:border-0">
                      <td className="px-2 py-3 font-medium text-foreground">{row.name}</td>
                      <td className="px-2 py-3 text-muted-foreground">{formatBytes(row.byte_size)}</td>
                      <td className="px-2 py-3 text-muted-foreground">{formatWhen(row.created_at)}</td>
                      <td className="px-2 py-3 text-right">
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          disabled={downloadMutation.isPending}
                          onClick={() => downloadMutation.mutate({ id: row.id, name: row.name })}
                        >
                          <Download className="size-4" />
                          Download
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
