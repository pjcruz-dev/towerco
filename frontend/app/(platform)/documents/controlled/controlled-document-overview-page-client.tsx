"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";

import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { fetchControlledDocumentOverview } from "@/lib/api/modules/controlled-documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";

function registerHref(params: Record<string, string>): string {
  const query = new URLSearchParams(params);
  const qs = query.toString();
  return qs ? `/documents/controlled/register?${qs}` : "/documents/controlled/register";
}

function Metric({
  label,
  value,
  href,
}: {
  label: string;
  value: number;
  href: string;
}) {
  return (
    <Link href={href} className="rounded-xl border border-border bg-card px-4 py-3 shadow-sm transition-colors hover:bg-muted/40">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-medium tabular-nums">{value}</p>
    </Link>
  );
}

export function ControlledDocumentOverviewPageClient() {
  const overviewQuery = useQuery({
    queryKey: ["documents", "controlled", "overview"],
    queryFn: fetchControlledDocumentOverview,
  });
  const counts = overviewQuery.data?.counts;

  return (
    <PermissionGate requiredPermissions={[permissions.documentsControlledView]}>
      <div className="space-y-6">
        <WorkspacePageHeader
          title="Document register"
          description="Published and obsolete controlled documents, reviews coming due, and what was published in the last 30 days."
          actions={
            <div className="flex flex-wrap items-center justify-end gap-1.5">
              <Button size="sm" render={<Link href="/documents/controlled/register" />}>
                Open register
              </Button>
              <Button size="sm" variant="outline" render={<Link href="/documents/controlled/reports" />}>
                Reports
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => void overviewQuery.refetch()}
                disabled={overviewQuery.isFetching}
              >
                {overviewQuery.isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
                Refresh
              </Button>
            </div>
          }
        />

        {overviewQuery.isError ? (
          <div className="space-y-3 rounded-xl border border-border bg-card px-4 py-8 text-center">
            <p className="text-sm text-foreground">{getErrorMessage(overviewQuery.error)}</p>
            <Button type="button" variant="outline" size="sm" onClick={() => void overviewQuery.refetch()}>
              Retry
            </Button>
          </div>
        ) : overviewQuery.isLoading ? (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            {Array.from({ length: 4 }, (_, index) => (
              <div key={index} className="h-20 animate-pulse rounded-xl border border-border bg-muted/40" />
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            <Metric label="Documents" value={counts?.total ?? 0} href={registerHref({})} />
            <Metric label="Pending" value={counts?.pending ?? 0} href={registerHref({ "filter[status]": "pending" })} />
            <Metric label="Published" value={counts?.published ?? 0} href={registerHref({ "filter[status]": "published" })} />
            <Metric label="Obsolete" value={counts?.obsolete ?? 0} href={registerHref({ "filter[status]": "obsolete" })} />
            <Metric
              label="Published · 30d"
              value={counts?.published_last_30_days ?? 0}
              href={registerHref({ "filter[status]": "published" })}
            />
            <Metric label="Overdue review" value={counts?.overdue ?? 0} href={registerHref({ "filter[review]": "overdue" })} />
            <Metric label="Due in 30 days" value={counts?.due_30 ?? 0} href={registerHref({ "filter[review]": "due_30" })} />
          </div>
        )}
      </div>
    </PermissionGate>
  );
}
