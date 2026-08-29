"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Clock, Download, Plus, RotateCcw, Trash2 } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformCreateTenantBackup,
  platformCronSyncTenantBackup,
  platformDeleteTenantBackup,
  platformDownloadTenantBackup,
  platformListTenantBackups,
  platformRestoreTenantBackup,
  type PlatformTenantBackupRow,
  type PlatformTenantRow,
} from "@/lib/api/modules/platform-api";
import { PLATFORM_PERMS, platformHasPermission } from "@/lib/platform/platform-permissions";
import { useNotificationStore } from "@/stores/notification-store";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";

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

function statusBadge(status: string) {
  const tone =
    status === "completed"
      ? "border-success/30 bg-success/10 text-success"
      : status === "failed"
        ? "border-destructive/30 bg-destructive/10 text-destructive"
        : status === "restoring" || status === "running" || status === "pending"
          ? "border-amber-300/50 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
          : "text-muted-foreground";

  return (
    <Badge variant="secondary" className={tone}>
      {status.replace(/_/g, " ")}
    </Badge>
  );
}

type Props = {
  tenant: PlatformTenantRow;
};

export function PlatformTenantBackupsPanel({ tenant }: Props) {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((s) => s.push);
  const platformUser = usePlatformAuthStore((s) => s.user);
  const canBackup = platformHasPermission(platformUser, PLATFORM_PERMS.tenantsBackup);

  const [restoreTarget, setRestoreTarget] = useState<PlatformTenantBackupRow | null>(null);
  const [confirm, setConfirm] = useState("");
  const [reason, setReason] = useState("");

  const confirmToken = (tenant.slug || tenant.brand_domain || "").trim();

  const backupsQuery = useQuery({
    queryKey: ["platform", "tenants", tenant.id, "backups"],
    queryFn: () => platformListTenantBackups(tenant.id),
    refetchInterval: (query) => {
      const rows = query.state.data?.data ?? [];
      const busy = rows.some((row) =>
        ["pending", "running", "restoring"].includes(row.status),
      );
      return busy ? 4000 : false;
    },
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenant.id, "backups"] });
    void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenant.id, "audit"] });
  };

  const createMutation = useMutation({
    mutationFn: () => platformCreateTenantBackup(tenant.id, { reason: "Manual backup" }),
    onSuccess: () => {
      invalidate();
      notify({ level: "success", title: "Backup queued", message: "Dump will appear when the worker finishes." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Backup failed", message: getErrorMessage(error) });
    },
  });

  const cronMutation = useMutation({
    mutationFn: () => platformCronSyncTenantBackup(tenant.id),
    onSuccess: () => {
      invalidate();
      notify({ level: "success", title: "Cron Sync queued", message: "Scheduled-style backup enqueued for this tenant." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Cron Sync failed", message: getErrorMessage(error) });
    },
  });

  const downloadMutation = useMutation({
    mutationFn: (row: PlatformTenantBackupRow) =>
      platformDownloadTenantBackup(tenant.id, row.id, row.name),
    onSuccess: () => {
      notify({ level: "success", title: "Download started", message: "Backup file saved to your device." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Download failed", message: getErrorMessage(error) });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (backupId: string) => platformDeleteTenantBackup(tenant.id, backupId),
    onSuccess: () => {
      invalidate();
      notify({ level: "success", title: "Backup deleted", message: "Archive removed from storage." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Delete failed", message: getErrorMessage(error) });
    },
  });

  const restoreMutation = useMutation({
    mutationFn: () => {
      if (!restoreTarget) throw new Error("No backup selected");
      return platformRestoreTenantBackup(tenant.id, restoreTarget.id, {
        confirm: confirm.trim(),
        reason: reason.trim(),
      });
    },
    onSuccess: (row) => {
      setRestoreTarget(null);
      setConfirm("");
      setReason("");
      invalidate();
      if (row.status === "completed" && !row.error_message) {
        notify({
          level: "success",
          title: "Restore completed",
          message: "Tenant database was replaced from the selected backup.",
        });
        return;
      }
      if (row.status === "completed" && row.error_message) {
        notify({
          level: "error",
          title: "Restore failed",
          message: row.error_message,
        });
        return;
      }
      notify({
        level: "warning",
        title: "Restore queued",
        message: "Tenant access is blocked until the restore job finishes. Refresh this tab shortly.",
      });
    },
    onError: (error) => {
      invalidate();
      notify({ level: "error", title: "Restore failed", message: getErrorMessage(error) });
    },
  });

  const meta = backupsQuery.data?.meta;
  const rows = backupsQuery.data?.data ?? [];
  const busy =
    createMutation.isPending || cronMutation.isPending || restoreMutation.isPending;

  return (
    <div className="space-y-4">
      <Card className="overflow-hidden rounded-xl border-slate-800 bg-slate-900 text-slate-50 shadow-sm">
        <CardContent className="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:justify-between">
          <div className="max-w-xl space-y-2">
            <h2 className="text-xl font-semibold tracking-tight">Data Protection Center</h2>
            <p className="text-sm text-slate-300">
              Logical MySQL dumps for this tenant database. Restore permanently replaces live tenant
              data. AWS RDS snapshots remain the infrastructure DR baseline.
            </p>
            <div className="flex flex-wrap gap-2 pt-1">
              {canBackup ? (
                <>
                  <Button
                    type="button"
                    variant="secondary"
                    className="bg-white text-slate-900 hover:bg-slate-100"
                    disabled={busy}
                    onClick={() => createMutation.mutate()}
                  >
                    <Plus className="size-4" />
                    New Backup
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    className="border-slate-600 bg-transparent text-slate-50 hover:bg-slate-800"
                    disabled={busy}
                    onClick={() => cronMutation.mutate()}
                  >
                    <Clock className="size-4" />
                    Cron Sync
                  </Button>
                </>
              ) : (
                <p className="text-xs text-slate-400">View-only — backup permission required to create or restore.</p>
              )}
            </div>
          </div>
          <div className="grid grid-cols-3 gap-3 text-center">
            <div className="rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-3">
              <p className="text-xs text-slate-400">Total</p>
              <p className="text-lg font-semibold">{meta?.total ?? 0}</p>
            </div>
            <div className="rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-3">
              <p className="text-xs text-slate-400">Latest</p>
              <p className="text-sm font-medium">
                {meta?.latest_at
                  ? new Date(meta.latest_at).toLocaleDateString(undefined, {
                      month: "short",
                      day: "numeric",
                    })
                  : "—"}
              </p>
            </div>
            <div className="rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-3">
              <p className="text-xs text-slate-400">Storage</p>
              <p className="text-sm font-medium">{formatBytes(meta?.storage_bytes)}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <div className="rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-700/50 dark:bg-amber-950/30 dark:text-amber-100">
        Restoring a backup permanently replaces current live data for this tenant. Backups older than{" "}
        {meta?.retention_days ?? 15} days are deleted automatically — download local copies if you need
        longer retention.
      </div>

      <Card className="rounded-xl shadow-sm">
        <CardHeader>
          <CardTitle className="text-base font-medium">Backups</CardTitle>
        </CardHeader>
        <CardContent>
          {backupsQuery.isLoading ? (
            <p className="text-sm text-muted-foreground">Loading backups…</p>
          ) : rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">No backups yet.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[640px] text-left text-sm">
                <thead className="border-b border-border text-xs text-muted-foreground">
                  <tr>
                    <th className="px-2 py-2 font-medium">Name</th>
                    <th className="px-2 py-2 font-medium">Status</th>
                    <th className="px-2 py-2 font-medium">Size</th>
                    <th className="px-2 py-2 font-medium">Created</th>
                    <th className="px-2 py-2 font-medium text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.id} className="border-b border-border/70 last:border-0">
                      <td className="px-2 py-3">
                        <div className="font-medium text-foreground">{row.name}</div>
                        {row.error_message ? (
                          <p
                            className="mt-1 max-w-md truncate text-xs text-destructive"
                            title={row.error_message}
                          >
                            {row.error_message}
                          </p>
                        ) : null}
                      </td>
                      <td className="px-2 py-3">{statusBadge(row.status)}</td>
                      <td className="px-2 py-3 text-muted-foreground">{formatBytes(row.byte_size)}</td>
                      <td className="px-2 py-3 text-muted-foreground">{formatWhen(row.created_at)}</td>
                      <td className="px-2 py-3">
                        <div className="flex justify-end gap-1">
                          <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            disabled={row.status !== "completed" || downloadMutation.isPending}
                            aria-label="Download backup"
                            onClick={() => downloadMutation.mutate(row)}
                          >
                            <Download className="size-4" />
                          </Button>
                          {canBackup ? (
                            <>
                              <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                disabled={row.status !== "completed" || busy}
                                aria-label="Restore backup"
                                onClick={() => {
                                  setRestoreTarget(row);
                                  setConfirm("");
                                  setReason("");
                                }}
                              >
                                <RotateCcw className="size-4" />
                              </Button>
                              <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                disabled={
                                  ["pending", "running", "restoring"].includes(row.status) ||
                                  deleteMutation.isPending
                                }
                                aria-label="Delete backup"
                                onClick={() => {
                                  if (window.confirm(`Delete backup ${row.name}?`)) {
                                    deleteMutation.mutate(row.id);
                                  }
                                }}
                              >
                                <Trash2 className="size-4" />
                              </Button>
                            </>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      <Dialog
        open={restoreTarget !== null}
        onOpenChange={(open) => {
          if (!open) {
            setRestoreTarget(null);
            setConfirm("");
            setReason("");
          }
        }}
      >
        <DialogContent className="gap-0">
          <DialogHeader>
            <DialogTitle>Restore tenant database</DialogTitle>
            <DialogDescription>
              This replaces all live data in the tenant database with{" "}
              <span className="font-medium text-foreground">{restoreTarget?.name}</span>. Type{" "}
              <span className="font-mono text-foreground">{confirmToken || "tenant slug"}</span> to
              confirm.
            </DialogDescription>
          </DialogHeader>
          <DialogBody className="space-y-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="restore-confirm" className="text-xs font-medium text-muted-foreground">
                Confirm slug / brand domain
              </Label>
              <Input
                id="restore-confirm"
                value={confirm}
                onChange={(e) => setConfirm(e.target.value)}
                placeholder={confirmToken}
                autoComplete="off"
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="restore-reason" className="text-xs font-medium text-muted-foreground">
                Reason
              </Label>
              <Textarea
                id="restore-reason"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                rows={3}
                placeholder="Incident / ticket reference"
              />
            </div>
          </DialogBody>
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => setRestoreTarget(null)}
              disabled={restoreMutation.isPending}
            >
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={
                restoreMutation.isPending ||
                confirm.trim().toLowerCase() !== confirmToken.toLowerCase() ||
                reason.trim().length < 3
              }
              onClick={() => restoreMutation.mutate()}
            >
              {restoreMutation.isPending ? "Restoring…" : "Restore now"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
