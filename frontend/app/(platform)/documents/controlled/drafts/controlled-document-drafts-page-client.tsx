"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";

import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { fetchControlledDocumentDrafts } from "@/lib/api/modules/controlled-documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";

function formatSavedAt(value: string | null): string {
  if (!value) {
    return "—";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "—";
  }
  return date.toLocaleString();
}

export function ControlledDocumentDraftsPageClient() {
  const draftsQuery = useQuery({
    queryKey: ["documents", "controlled", "drafts"],
    queryFn: fetchControlledDocumentDrafts,
  });
  const items = draftsQuery.data ?? [];

  return (
    <PermissionGate requiredPermissions={[permissions.documentsControlledView]}>
      <div className="space-y-4">
        <WorkspacePageHeader
          title="Drafts"
          description="Unsent document requests you saved. Continue opens the request in Document Control."
          actions={
            <Button size="sm" variant="outline" render={<Link href="/documents/controlled" />}>
              Overview
            </Button>
          }
        />

        {draftsQuery.isLoading ? (
          <div className="flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-8 text-sm text-muted-foreground">
            <Spinner className="size-4" />
            Loading drafts
          </div>
        ) : draftsQuery.isError ? (
          <div className="space-y-3 rounded-xl border border-border bg-card px-4 py-8 text-center">
            <p className="text-sm text-foreground">{getErrorMessage(draftsQuery.error)}</p>
            <Button type="button" variant="outline" size="sm" onClick={() => void draftsQuery.refetch()}>
              Retry
            </Button>
          </div>
        ) : items.length === 0 ? (
          <div className="rounded-xl border border-border bg-card px-4 py-8 text-center">
            <p className="text-sm font-medium text-foreground">No drafts</p>
            <p className="mt-1 text-sm text-muted-foreground">Requests you save and have not submitted will appear here.</p>
          </div>
        ) : (
          <ul className="divide-y divide-border rounded-xl border border-border bg-card">
            {items.map((item) => (
              <li key={item.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-foreground">{item.document_no || "Draft"}</p>
                  <p className="truncate text-xs text-muted-foreground">
                    {item.form_name ? `${item.form_name} · ` : ""}
                    Saved {formatSavedAt(item.updated_at)}
                  </p>
                </div>
                <Button size="sm" render={<Link href={item.href} />}>
                  Continue
                </Button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </PermissionGate>
  );
}
