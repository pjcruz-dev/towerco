"use client";

import Link from "next/link";
import { useMemo } from "react";
import {
  ArrowRight,
  ClipboardCheck,
  FolderKanban,
  MapPinned,
  Rocket,
  ShieldCheck,
} from "lucide-react";

import { RolloutRecentPanel } from "@/components/project-one/rollout-recent-panel";
import { MyWorkStrip } from "@/components/project-one/my-work-strip";
import { ActionableWidgets } from "@/components/project-one/actionable-widgets";
import { GateApprovalsPendingWidget } from "@/components/project-one/gate-approvals-pending-widget";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { countBy, DASHBOARD_CHART } from "@/components/dashboard/dashboard-chart-utils";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import { MapPanel } from "@/components/project-one/map-panel";
import { MilestoneTracker } from "@/components/project-one/milestone-tracker";
import { PendingApprovalsTable } from "@/components/project-one/pending-approvals-table";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { usePermission } from "@/hooks/use-permission";
import { useProjectOneDashboard } from "@/hooks/use-project-one-dashboard";
import { useRolloutRealtime } from "@/hooks/use-rollout-realtime";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

const milestoneStatusLabel: Record<string, string> = {
  on_track: "On track",
  at_risk: "At risk",
  blocked: "Blocked",
};

export function ProjectOneDashboardPageClient() {
  const { data, isFetching, isMapLoading, isError, isMapError, isPlaceholderData, refetch } =
    useProjectOneDashboard();
  const showSkeleton = isFetching && isPlaceholderData;
  const canManageApprovals = usePermission([permissions.projectOneManage]);
  const canViewRollouts = usePermission([permissions.rolloutView]);
  useRolloutRealtime();

  const programApprovalsPending =
    data?.kpis.find((kpi) => kpi.key === "pending_approvals")?.value ??
    String(data?.approvals.length ?? 0);

  const gateAwaiting = data?.rollouts?.gate_approvals_awaiting_me ?? 0;

  const rolloutsByProject = useMemo(
    () =>
      (data?.rollouts?.active_rollouts_by_project ?? []).map((row) => ({
        key: row.project_id ?? row.project_name,
        label: row.project_name,
        value: row.active_rollouts,
      })),
    [data?.rollouts?.active_rollouts_by_project],
  );

  const milestoneStatusSeries = useMemo(
    () =>
      countBy(
        data?.milestones ?? [],
        (m) => m.status,
        (key) => milestoneStatusLabel[key] ?? key,
      ).map((row) => ({
        ...row,
        fill:
          row.key === "blocked"
            ? DASHBOARD_CHART.danger
            : row.key === "at_risk"
              ? DASHBOARD_CHART.warning
              : DASHBOARD_CHART.success,
      })),
    [data?.milestones],
  );

  const shortcuts = useMemo(
    () =>
      [
        {
          href: "/project-one/gate-approvals?awaiting_me=1",
          label: "Gate approvals",
          description: "Decide timeline gates assigned to you",
          icon: ShieldCheck,
          show: canViewRollouts,
        },
        {
          href: "/project-one/approvals",
          label: "Program approvals",
          description: "Review pending program decisions",
          icon: ClipboardCheck,
          show: true,
        },
        {
          href: "/project-one/rollouts",
          label: "Rollouts",
          description: "Track active site rollouts",
          icon: Rocket,
          show: canViewRollouts,
        },
        {
          href: "/project-one/projects",
          label: "Projects",
          description: "Browse programs and milestones",
          icon: FolderKanban,
          show: true,
        },
        {
          href: "/sites",
          label: "Sites",
          description: "Site registry and map context",
          icon: MapPinned,
          show: true,
        },
      ].filter((item) => item.show),
    [canViewRollouts],
  );

  return (
    <PermissionGate requiredPermissions={[permissions.projectOneView]}>
      <div className="space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Project-One</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Your program inbox — gates, approvals, and map-first rollout visibility.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Button size="sm" variant="outline" type="button" onClick={() => refetch()} disabled={isFetching}>
              {isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
              Refresh
            </Button>
            {canViewRollouts ? (
              <Link
                href="/project-one/gate-approvals?awaiting_me=1"
                className={buttonVariants({ size: "sm", variant: "outline" })}
              >
                <ShieldCheck className="mr-1.5 size-3.5" aria-hidden />
                Gate inbox
                {gateAwaiting > 0 ? ` (${gateAwaiting})` : ""}
              </Link>
            ) : null}
            {canManageApprovals ? (
              <Link href="/project-one/approvals/new" className={buttonVariants({ size: "sm" })}>
                Create approval
              </Link>
            ) : null}
          </div>
        </header>

        {showSkeleton ? <DashboardContentSkeleton /> : null}

        {!showSkeleton ? (
          <>
            {/* 1. Personal action strip */}
            <MyWorkStrip
              gateApprovalsAwaitingMe={gateAwaiting}
              programApprovalsPending={Number.parseInt(programApprovalsPending, 10) || 0}
              rolloutsSlaAtRisk={data?.rollouts?.sla_at_risk ?? 0}
            />

            {/* 2. Portfolio KPIs */}
            <section className="space-y-2">
              <div>
                <h2 className="text-sm font-medium text-foreground">Portfolio snapshot</h2>
                <p className="text-xs text-muted-foreground">
                  Tenant-wide program health. Use My work above for items assigned to you.
                </p>
              </div>
              <KpiStrip items={data?.kpis ?? []} />
            </section>

            {/* 3. Decision queues (before map noise) */}
            <div
              className={cn(
                "grid gap-4",
                canViewRollouts ? "lg:grid-cols-2" : "lg:grid-cols-1",
              )}
            >
              {canViewRollouts ? (
                <GateApprovalsPendingWidget
                  awaitingCount={gateAwaiting}
                  preview={data?.rollouts?.gate_approvals_preview ?? []}
                  alwaysShow
                />
              ) : null}
              <ActionableWidgets items={data?.actions ?? []} />
            </div>

            {/* 4. Map-first operational plane */}
            <section className="space-y-2">
              <div>
                <h2 className="text-sm font-medium text-foreground">Operational map</h2>
                <p className="text-xs text-muted-foreground">
                  Site and rollout status across the network.
                </p>
              </div>
              <MapPanel
                pins={data?.map_pins}
                sites={data?.sites ?? []}
                isLoading={isMapLoading}
              />
            </section>

            {/* 5. Trackers */}
            <div className="grid gap-4 lg:grid-cols-2">
              <PendingApprovalsTable approvals={data?.approvals ?? []} />
              <MilestoneTracker items={data?.milestones ?? []} />
            </div>

            {/* 6. Portfolio charts (secondary insight) */}
            {canViewRollouts || (data?.milestones?.length ?? 0) > 0 ? (
              <section className="space-y-2">
                <div>
                  <h2 className="text-sm font-medium text-foreground">Program mix</h2>
                  <p className="text-xs text-muted-foreground">
                    Rollout load and milestone health — secondary to your queues.
                  </p>
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                  {canViewRollouts ? (
                    <DashboardBarChart
                      title="Active rollouts by project"
                      description="Current rollout load across programs"
                      data={rolloutsByProject}
                      layout="horizontal"
                      emptyMessage="No active rollouts to chart."
                      height={200}
                    />
                  ) : null}
                  <DashboardDonutChart
                    title="Milestone health"
                    description="On track, at risk, and blocked milestones"
                    data={milestoneStatusSeries}
                    emptyMessage="No milestones to chart."
                    height={200}
                  />
                </div>
              </section>
            ) : null}

            {canViewRollouts && data?.rollouts ? <RolloutRecentPanel rollouts={data.rollouts} /> : null}

            {/* 7. Compact shortcuts */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
              {shortcuts.map((tile) => {
                const Icon = tile.icon;
                return (
                  <Link
                    key={tile.href}
                    href={tile.href}
                    className="group flex items-start gap-3 rounded-xl border border-border bg-card p-3.5 shadow-sm transition-colors hover:border-primary/30 hover:bg-muted/30"
                  >
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground">
                      <Icon className="size-4" aria-hidden />
                    </div>
                    <div className="min-w-0">
                      <p className="text-sm font-medium text-foreground">{tile.label}</p>
                      <p className="text-xs text-muted-foreground">{tile.description}</p>
                    </div>
                    <ArrowRight className="ml-auto size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                  </Link>
                );
              })}
            </div>
          </>
        ) : null}

        {isError ? (
          <p className="text-sm text-destructive">
            Unable to sync Project-One dashboard data. Check API connectivity.
          </p>
        ) : null}
        {isMapError ? (
          <p className="text-sm text-amber-700 dark:text-amber-300">
            Map pins could not be loaded. KPIs are still available — try Refresh.
          </p>
        ) : null}
      </div>
    </PermissionGate>
  );
}
