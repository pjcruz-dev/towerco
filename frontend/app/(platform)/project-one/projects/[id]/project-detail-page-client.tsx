"use client";

import Link from "next/link";
import { useMemo, type ReactNode } from "react";

import {
  createProjectDetailApprovalColumns,
  createProjectDetailMilestoneColumns,
  createProjectDetailRolloutColumns,
} from "@/components/project-one/project-detail-nested-table-columns";
import { RaiseTicketButton } from "@/components/ticketing/raise-ticket-button";
import { TicketingRelatedTickets } from "@/components/ticketing/ticketing-related-tickets";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { usePermission } from "@/hooks/use-permission";
import { Button, buttonVariants } from "@/components/ui/button";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { useProjectOneProjectDetail } from "@/hooks/use-project-one-project-detail";
import { permissions } from "@/lib/rbac/permissions";

export function ProjectDetailPageClient({ projectId }: { projectId: string }) {
  const { data, isFetching, isError, refetch } = useProjectOneProjectDetail(projectId);
  const canManageProject = usePermission([permissions.projectOneManage]);
  const canManageRollout = usePermission([permissions.rolloutManage]);

  const milestoneColumns = useMemo(
    () => createProjectDetailMilestoneColumns(projectId),
    [projectId],
  );
  const rolloutColumns = useMemo(() => createProjectDetailRolloutColumns(), []);
  const approvalColumns = useMemo(() => createProjectDetailApprovalColumns(), []);

  const milestones = data?.milestones ?? [];
  const rollouts = data?.rollouts ?? [];
  const approvals = data?.approvals ?? [];

  return (
    <PermissionGate requiredPermissions={[permissions.projectOneView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">{data?.name ?? "Project"}</h1>
            <p className="mt-1 text-sm capitalize text-muted-foreground">
              {data?.status?.replaceAll("_", " ") ?? "—"}
              {data?.site ? ` · ${data.site.site_code} · ${data.site.name}` : ""}
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
              {data?.start_date ?? "—"} → {data?.end_date ?? "—"}
              {data?.project_manager ? ` · PM ${data.project_manager.name}` : ""}
            </p>
            {data?.site ? (
              <p className="mt-2 text-xs font-medium">
                <Link className="text-primary underline-offset-4 hover:underline" href="/sites">
                  Open site {data.site.site_code}
                </Link>
              </p>
            ) : null}
          </div>
          <div className="flex flex-wrap gap-2">
            {canManageRollout && projectId ? (
              <Link
                href={`/project-one/rollouts/new?project_id=${encodeURIComponent(projectId)}`}
                className={buttonVariants({ size: "sm" })}
              >
                New rollout
              </Link>
            ) : null}
            {canManageProject && projectId ? (
              <Link
                href={`/project-one/approvals/new?project_id=${encodeURIComponent(projectId)}`}
                className={buttonVariants({ size: "sm", variant: "outline" })}
              >
                New approval
              </Link>
            ) : null}
            {data ? (
              <RaiseTicketButton
                prefill={{
                  title: `Project issue — ${data.name}`,
                  description: [
                    `Project: ${data.name}`,
                    `Status: ${data.status}`,
                    data.site ? `Site: ${data.site.site_code}` : null,
                    `Link: /project-one/projects/${projectId}`,
                  ]
                    .filter(Boolean)
                    .join("\n"),
                  source_module: "project_one",
                  source_reference_type: "project",
                  source_reference_id: projectId,
                  source_label: data.name,
                  links: [
                    {
                      link_module: "project_one",
                      link_type: "project",
                      link_id: projectId,
                      link_label: data.name,
                    },
                  ],
                }}
              />
            ) : null}
            <Button size="sm" variant="outline" onClick={() => refetch()}>
              Refresh
            </Button>
          </div>
        </header>

        <TicketingRelatedTickets sourceModule="project_one" sourceReferenceId={projectId} />

        <div className="grid gap-3 sm:grid-cols-3">
          <MetricCard label="Milestones" value={String(data?.milestones.length ?? 0)} />
          <MetricCard label="Linked rollouts" value={String(data?.rollout_count ?? 0)} />
          <MetricCard label="Recent approvals" value={String(data?.approvals.length ?? 0)} />
        </div>

        <Section title="Milestones">
          <RegistryDataTableView
            columns={milestoneColumns}
            data={milestones}
            getRowId={(row) => row.id}
            isLoading={isFetching && !data}
            isEmpty={!isFetching && milestones.length === 0}
            emptyMessage="No milestones on this project."
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.project-one.project-detail.milestones"
            scrollClassName="max-h-none"
          />
        </Section>

        <Section title="Linked rollouts">
          <RegistryDataTableView
            columns={rolloutColumns}
            data={rollouts}
            getRowId={(row) => row.id}
            isLoading={isFetching && !data}
            isEmpty={!isFetching && rollouts.length === 0}
            emptyContent={
              <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                No rollouts linked yet.{" "}
                {canManageRollout ? (
                  <Link
                    className="text-primary underline-offset-4 hover:underline"
                    href={`/project-one/rollouts/new?project_id=${encodeURIComponent(projectId)}`}
                  >
                    Create rollout for this project
                  </Link>
                ) : null}
              </div>
            }
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.project-one.project-detail.rollouts"
            scrollClassName="max-h-none"
          />
        </Section>

        <Section title="Recent approvals">
          <RegistryDataTableView
            columns={approvalColumns}
            data={approvals}
            getRowId={(row) => row.id}
            isLoading={isFetching && !data}
            isEmpty={!isFetching && approvals.length === 0}
            emptyMessage="No approvals for this project."
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.project-one.project-detail.approvals"
            scrollClassName="max-h-none"
          />
        </Section>

        {isFetching ? <RefreshingHint label="Loading project" /> : null}
        {isError ? <p className="text-sm text-destructive">Could not load project detail.</p> : null}
      </div>
    </PermissionGate>
  );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
      <div className="border-b border-border px-4 py-3">
        <h2 className="text-base font-medium text-foreground">{title}</h2>
      </div>
      {children}
    </div>
  );
}

function MetricCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <p className="mt-1 text-base font-medium text-foreground">{value}</p>
    </div>
  );
}
