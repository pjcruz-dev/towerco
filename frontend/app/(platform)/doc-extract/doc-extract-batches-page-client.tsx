"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, FileScan, Plus, RotateCcw, ScrollText, Search } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import { Spinner } from "@/components/ui/spinner";
import { fetchDocExtractBatches, requeueDocExtractBatch } from "@/lib/api/modules/doc-extract-api";
import { getErrorMessage } from "@/lib/api/error";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import type { DocExtractBatchListRow } from "@/modules/doc-extract/types";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type StatusFilter = "all" | "processing" | "ready" | "failed";

function statusTone(status: string): string {
  if (status === "ready") {
    return "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-400";
  }
  if (status === "failed") {
    return "border-red-200 bg-red-50 text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-400";
  }
  if (status === "processing" || status === "pending") {
    return "border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-400";
  }
  return "border-border bg-muted text-muted-foreground";
}

function statusLabel(status: string): string {
  if (status === "pending") return "Queued";
  if (status === "processing") return "Scanning";
  if (status === "ready") return "Ready";
  if (status === "failed") return "Failed";
  return status;
}

function formatWhen(value?: string | null): { absolute: string; relative: string } {
  if (!value) return { absolute: "—", relative: "—" };
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return { absolute: "—", relative: "—" };

  const absolute = date.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });

  const deltaMs = Date.now() - date.getTime();
  const minutes = Math.floor(deltaMs / 60_000);
  if (minutes < 1) return { absolute, relative: "Just now" };
  if (minutes < 60) return { absolute, relative: `${minutes}m ago` };
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return { absolute, relative: `${hours}h ago` };
  const days = Math.floor(hours / 24);
  if (days < 7) return { absolute, relative: `${days}d ago` };
  return { absolute, relative: absolute };
}

function matchesStatus(batch: DocExtractBatchListRow, filter: StatusFilter): boolean {
  if (filter === "all") return true;
  if (filter === "processing") return batch.status === "processing" || batch.status === "pending";
  return batch.status === filter;
}

function BatchProgress({ batch }: { batch: DocExtractBatchListRow }) {
  const total = Math.max(0, batch.document_count);
  const ready = Math.max(0, batch.ready_count);
  const failed = Math.max(0, batch.failed_count);
  const done = Math.min(total, ready + failed);
  const readyPct = total > 0 ? (ready / total) * 100 : 0;
  const failedPct = total > 0 ? (failed / total) * 100 : 0;
  const pending = Math.max(0, total - done);

  return (
    <div className="min-w-[10rem] space-y-1.5">
      <div className="flex items-baseline justify-between gap-2 text-[12px]">
        <span className="tabular-nums text-foreground">
          {ready}/{total} ready
        </span>
        {failed > 0 ? (
          <span className="tabular-nums text-red-600 dark:text-red-400">{failed} failed</span>
        ) : pending > 0 ? (
          <span className="tabular-nums text-muted-foreground">{pending} left</span>
        ) : null}
      </div>
      <div className="h-1.5 overflow-hidden rounded-full bg-muted">
        <div className="flex h-full w-full">
          <div className="h-full bg-emerald-500 transition-[width]" style={{ width: `${readyPct}%` }} />
          <div className="h-full bg-red-500 transition-[width]" style={{ width: `${failedPct}%` }} />
        </div>
      </div>
    </div>
  );
}

export function DocExtractBatchesPageClient() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const user = useAuthStore((state) => state.user);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const scopedUser = user ? { ...user, permissions: effectivePermissions() } : null;
  const canManageTemplates = hasPermission(scopedUser, [permissions.docExtractTemplatesManage]);
  const canRun = hasPermission(scopedUser, [permissions.docExtractRun]);

  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
  const [search, setSearch] = useState("");
  const [requeueBatchId, setRequeueBatchId] = useState<string | null>(null);

  const query = useQuery({
    queryKey: ["doc-extract", "batches"],
    queryFn: () => fetchDocExtractBatches({ page: 1, per_page: 50 }),
    refetchInterval: (state) => {
      const rows = state.state.data?.data ?? [];
      return rows.some((row) => row.status === "processing" || row.status === "pending") ? 4000 : false;
    },
  });

  const requeueMutation = useMutation({
    mutationFn: requeueDocExtractBatch,
    onMutate: (batchId) => setRequeueBatchId(batchId),
    onSuccess: async (result) => {
      notify({
        level: "success",
        title: "Scans requeued",
        message:
          result.requeued > 0
            ? `${result.requeued} document(s) sent back to the OCR queue.`
            : "No pending documents needed a retry.",
      });
      await queryClient.invalidateQueries({ queryKey: ["doc-extract", "batches"] });
    },
    onError: (error) => {
      notify({ level: "error", title: "Could not requeue", message: getErrorMessage(error) });
    },
    onSettled: () => setRequeueBatchId(null),
  });

  const rows = query.data?.data ?? [];

  const summary = useMemo(() => {
    let processing = 0;
    let ready = 0;
    let failed = 0;
    for (const row of rows) {
      if (row.status === "processing" || row.status === "pending") processing += 1;
      else if (row.status === "ready") ready += 1;
      else if (row.status === "failed") failed += 1;
    }
    return { processing, ready, failed, total: rows.length };
  }, [rows]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return rows.filter((batch) => {
      if (!matchesStatus(batch, statusFilter)) return false;
      if (!q) return true;
      const template = (batch.template_name ?? "auto-detect").toLowerCase();
      const mode = (batch.mode ?? "").toLowerCase();
      const id = batch.id.toLowerCase();
      return template.includes(q) || mode.includes(q) || id.includes(q);
    });
  }, [rows, search, statusFilter]);

  const filters: { id: StatusFilter; label: string; count: number }[] = [
    { id: "all", label: "All", count: summary.total },
    { id: "processing", label: "Scanning", count: summary.processing },
    { id: "ready", label: "Ready", count: summary.ready },
    { id: "failed", label: "Failed", count: summary.failed },
  ];

  return (
    <PermissionGate requiredPermissions={[permissions.docExtractView]}>
      <div className="w-full space-y-5" data-help="dx-batches-page">
        <LiveProductTourHost />

        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold text-foreground">DocExtract</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Upload → consolidate pages → extract → customize columns → export. Source files purge after 7 days;
              filenames stay for audit.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {canManageTemplates ? (
              <Button size="sm" variant="outline" render={<Link href="/doc-extract/templates" />}>
                <ScrollText className="size-4" />
                Templates
              </Button>
            ) : null}
            {canRun ? (
              <Button size="sm" data-help="dx-new-batch" render={<Link href="/doc-extract/new" />}>
                <Plus className="size-4" />
                New extraction
              </Button>
            ) : null}
          </div>
        </div>

        {query.isLoading ? (
          <DashboardContentSkeleton />
        ) : query.isError ? (
          <div className="rounded-xl border border-border bg-card p-6 text-sm text-destructive shadow-sm">
            {getErrorMessage(query.error)}
            <Button type="button" variant="outline" size="sm" className="mt-3" onClick={() => query.refetch()}>
              Retry
            </Button>
          </div>
        ) : rows.length === 0 ? (
          <div className="rounded-xl border border-dashed border-border bg-card p-10 text-center shadow-sm">
            <FileScan className="mx-auto size-8 text-muted-foreground" />
            <p className="mt-3 text-sm font-medium text-foreground">No extractions yet</p>
            <p className="mt-1 text-sm text-muted-foreground">
              Start with PDFs or images, arrange pages in Consolidate, then review and export.
            </p>
            {canRun ? (
              <Button size="sm" className="mt-4" render={<Link href="/doc-extract/new" />}>
                <Plus className="size-4" />
                New extraction
              </Button>
            ) : null}
          </div>
        ) : (
          <>
            {summary.processing > 0 ? (
              <button
                type="button"
                onClick={() => setStatusFilter("processing")}
                className="flex w-full items-center gap-3 rounded-xl border border-sky-200 bg-sky-50/80 px-4 py-3 text-left text-sm text-sky-900 shadow-sm transition-colors hover:bg-sky-50 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100 dark:hover:bg-sky-950/50"
              >
                <Spinner className="size-4 shrink-0" />
                <span className="font-medium">
                  {summary.processing} extraction{summary.processing === 1 ? "" : "s"} scanning
                </span>
                <span className="text-sky-700/80 dark:text-sky-300/80">Click to filter · auto-refreshes</span>
              </button>
            ) : null}

            {summary.failed > 0 && statusFilter !== "failed" ? (
              <button
                type="button"
                onClick={() => setStatusFilter("failed")}
                className="flex w-full items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-left text-sm text-amber-950 shadow-sm transition-colors hover:bg-amber-50 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100 dark:hover:bg-amber-950/50"
              >
                <AlertTriangle className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                <span className="font-medium">
                  {summary.failed} extraction{summary.failed === 1 ? "" : "s"} failed
                </span>
                <span className="text-amber-800/70 dark:text-amber-200/70">Review and retry from the batch</span>
              </button>
            ) : null}

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex flex-wrap gap-1.5">
                {filters.map((filter) => {
                  const active = statusFilter === filter.id;
                  return (
                    <Button
                      key={filter.id}
                      type="button"
                      size="sm"
                      variant={active ? "default" : "outline"}
                      className={cn("h-8 gap-1.5", !active && "bg-card")}
                      onClick={() => setStatusFilter(filter.id)}
                    >
                      {filter.label}
                      <span
                        className={cn(
                          "rounded px-1.5 py-0 text-[11px] tabular-nums",
                          active ? "bg-primary-foreground/15 text-primary-foreground" : "bg-muted text-muted-foreground",
                        )}
                      >
                        {filter.count}
                      </span>
                    </Button>
                  );
                })}
              </div>
              <div className="relative w-full sm:max-w-xs">
                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Search template or batch id"
                  className="h-8 pl-8"
                  aria-label="Search extractions"
                />
              </div>
            </div>

            {filtered.length === 0 ? (
              <div className="rounded-xl border border-dashed border-border bg-card px-6 py-10 text-center text-sm text-muted-foreground shadow-sm">
                No extractions match this filter.
                <div className="mt-3">
                  <Button type="button" size="sm" variant="outline" onClick={() => { setStatusFilter("all"); setSearch(""); }}>
                    Clear filters
                  </Button>
                </div>
              </div>
            ) : (
              <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div className="overflow-x-auto">
                  <table className="min-w-full text-left text-[13px]">
                    <thead className="sticky top-0 z-10 border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground backdrop-blur-sm">
                      <tr>
                        <th className="px-4 py-3 whitespace-nowrap">Created</th>
                        <th className="px-4 py-3 whitespace-nowrap">Mode</th>
                        <th className="px-4 py-3 whitespace-nowrap">Status</th>
                        <th className="px-4 py-3 whitespace-nowrap min-w-[12rem]">Progress</th>
                        <th className="px-4 py-3 whitespace-nowrap">Notes</th>
                        <th className="px-4 py-3" />
                      </tr>
                    </thead>
                    <tbody>
                      {filtered.map((batch) => {
                        const when = formatWhen(batch.created_at);
                        const href = `/doc-extract/batches/${batch.id}`;
                        const isLive = batch.status === "processing" || batch.status === "pending";
                        return (
                          <tr
                            key={batch.id}
                            className={cn(
                              "border-b border-border last:border-0 hover:bg-muted/30",
                              isLive && "bg-sky-50/40 dark:bg-sky-950/20",
                            )}
                          >
                            <td className="px-4 py-3 align-middle">
                              <Link href={href} className="block min-w-[7rem]">
                                <div className="font-medium text-foreground">{when.relative}</div>
                                <div className="mt-0.5 text-[11px] text-muted-foreground">{when.absolute}</div>
                              </Link>
                            </td>
                            <td className="px-4 py-3 align-middle">
                              <div className="font-medium text-foreground">
                                {batch.template_name ?? "Auto-detect"}
                              </div>
                              <div className="mt-0.5 text-[11px] capitalize text-muted-foreground">
                                {(batch.mode ?? (batch.template_id ? "template" : "auto")).replace("_", " ")}
                              </div>
                            </td>
                            <td className="px-4 py-3 align-middle">
                              <span
                                className={cn(
                                  "inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium",
                                  statusTone(batch.status),
                                )}
                              >
                                {isLive ? <Spinner className="size-3" /> : null}
                                {statusLabel(batch.status)}
                              </span>
                            </td>
                            <td className="px-4 py-3 align-middle">
                              <BatchProgress batch={batch} />
                            </td>
                            <td className="px-4 py-3 align-middle text-muted-foreground">
                              <span className="line-clamp-2 max-w-[16rem]" title={batch.message ?? undefined}>
                                {batch.message?.trim() || "—"}
                              </span>
                            </td>
                            <td className="px-4 py-3 align-middle text-right">
                              <div className="flex items-center justify-end gap-1">
                                {canRun &&
                                (batch.status === "processing" || batch.status === "pending") &&
                                batch.ready_count + batch.failed_count < batch.document_count ? (
                                  <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={requeueMutation.isPending}
                                    onClick={() => requeueMutation.mutate(batch.id)}
                                    title="Re-queue pending/scanning documents if the worker lost jobs"
                                  >
                                    {requeueBatchId === batch.id ? (
                                      <Spinner className="size-3.5" />
                                    ) : (
                                      <RotateCcw className="size-3.5" />
                                    )}
                                    Retry
                                  </Button>
                                ) : null}
                                <Button size="sm" variant="ghost" render={<Link href={href} />}>
                                  Open
                                </Button>
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
                <div className="border-t border-border px-4 py-2 text-right text-[11px] text-muted-foreground">
                  Showing {filtered.length} of {rows.length} · max 50 recent
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </PermissionGate>
  );
}
