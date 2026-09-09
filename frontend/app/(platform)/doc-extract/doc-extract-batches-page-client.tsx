"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { FileScan, Plus, ScrollText } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { Button } from "@/components/ui/button";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import { Spinner } from "@/components/ui/spinner";
import { fetchDocExtractBatches } from "@/lib/api/modules/doc-extract-api";
import { getErrorMessage } from "@/lib/api/error";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";

function statusTone(status: string): string {
  if (status === "ready") return "text-emerald-700 bg-emerald-50 border-emerald-200";
  if (status === "failed") return "text-red-700 bg-red-50 border-red-200";
  if (status === "processing" || status === "pending") return "text-sky-700 bg-sky-50 border-sky-200";
  return "text-muted-foreground bg-muted border-border";
}

export function DocExtractBatchesPageClient() {
  const user = useAuthStore((state) => state.user);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const scopedUser = user ? { ...user, permissions: effectivePermissions() } : null;
  const canManageTemplates = hasPermission(scopedUser, [permissions.docExtractTemplatesManage]);
  const canRun = hasPermission(scopedUser, [permissions.docExtractRun]);

  const query = useQuery({
    queryKey: ["doc-extract", "batches"],
    queryFn: () => fetchDocExtractBatches({ page: 1, per_page: 50 }),
    refetchInterval: (state) => {
      const rows = state.state.data?.data ?? [];
      return rows.some((row) => row.status === "processing" || row.status === "pending") ? 4000 : false;
    },
  });

  return (
    <PermissionGate requiredPermissions={[permissions.docExtractView]}>
      <div className="space-y-6" data-help="dx-batches-page">
        <LiveProductTourHost />
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold text-foreground">DocExtract</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Upload → customize fields → view results. Export CSV or XLSX. Source files are removed after 7 days;
              filenames are kept.
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
          <div className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">
            {getErrorMessage(query.error)}
            <Button type="button" variant="outline" size="sm" className="mt-3" onClick={() => query.refetch()}>
              Retry
            </Button>
          </div>
        ) : (query.data?.data.length ?? 0) === 0 ? (
          <div className="rounded-xl border border-dashed border-border bg-card p-10 text-center">
            <FileScan className="mx-auto size-8 text-muted-foreground" />
            <p className="mt-3 text-sm font-medium text-foreground">No extractions yet</p>
            <p className="mt-1 text-sm text-muted-foreground">
              Upload PDFs or images — auto-detect fields, then customize and export.
            </p>
            {canRun ? (
              <Button size="sm" className="mt-4" render={<Link href="/doc-extract/new" />}>
                <Plus className="size-4" />
                New extraction
              </Button>
            ) : null}
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-border bg-card">
            <table className="w-full text-left text-[13px]">
              <thead className="sticky top-0 border-b border-border bg-muted/40 text-xs font-medium text-muted-foreground">
                <tr>
                  <th className="px-4 py-3">Created</th>
                  <th className="px-4 py-3">Template</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Documents</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody>
                {query.data?.data.map((batch) => (
                  <tr key={batch.id} className="border-b border-border last:border-0">
                    <td className="px-4 py-3 text-foreground">
                      {batch.created_at ? new Date(batch.created_at).toLocaleString() : "—"}
                    </td>
                    <td className="px-4 py-3">{batch.template_name ?? "Auto-detect"}</td>
                    <td className="px-4 py-3">
                      <span
                        className={cn(
                          "inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium capitalize",
                          statusTone(batch.status),
                        )}
                      >
                        {(batch.status === "processing" || batch.status === "pending") && (
                          <Spinner className="size-3" />
                        )}
                        {batch.status}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">
                      {batch.ready_count}/{batch.document_count} ready
                      {batch.failed_count > 0 ? ` · ${batch.failed_count} failed` : ""}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <Button size="sm" variant="ghost" render={<Link href={`/doc-extract/batches/${batch.id}`} />}>
                        Open
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}
