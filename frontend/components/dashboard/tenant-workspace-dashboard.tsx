"use client";

import Link from "next/link";
import { useMemo } from "react";

import { AwaitingMeHub } from "@/components/dashboard/awaiting-me-hub";
import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { chartColorAt, kpiSeries } from "@/components/dashboard/dashboard-chart-utils";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { ActionableWidgets } from "@/components/project-one/actionable-widgets";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { useWorkspaceDashboard } from "@/hooks/use-workspace-dashboard";
import { emptyWorkspaceDashboard } from "@/lib/api/modules/workspace-dashboard-api";
import { cn } from "@/lib/utils";
import type { WorkspaceDashboardActivity } from "@/modules/workspace/types";

const moduleLabels: Record<string, string> = {
  e_approval: "E-Approval",
  project_one: "PROJECT-ONE",
  ticketing: "Ticketing",
  notifications: "Notifications",
};

function formatActivityTime(iso: string | null): string {
  if (!iso) {
    return "—";
  }
  try {
    return new Intl.DateTimeFormat(undefined, {
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    }).format(new Date(iso));
  } catch {
    return iso;
  }
}

function RecentActivityPanel({ items }: { items: WorkspaceDashboardActivity[] }) {
  return (
    <section className="rounded-xl border bg-card p-4 shadow-sm">
      <h2 className="text-base font-medium text-foreground">Recent activity</h2>
      <p className="mt-1 text-xs text-muted-foreground">
        Latest notifications and workflow updates for your account.
      </p>

      <div className="mt-4 divide-y divide-border">
        {items.length === 0 ? (
          <p className="py-6 text-sm text-muted-foreground">No recent activity yet.</p>
        ) : (
          items.map((item) => {
            const content = (
              <div className="py-3">
                <div className="flex items-center justify-between gap-2">
                  <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                    {moduleLabels[item.module] ?? item.module}
                  </span>
                  <span className="font-mono text-[10px] text-muted-foreground">
                    {formatActivityTime(item.created_at)}
                  </span>
                </div>
                <p className="mt-1 text-sm font-medium text-foreground">{item.label}</p>
                {item.detail ? (
                  <p className="mt-0.5 text-xs text-muted-foreground">{item.detail}</p>
                ) : null}
              </div>
            );

            if (item.href) {
              return (
                <Link
                  key={item.id}
                  href={item.href}
                  className="block transition-colors hover:bg-muted/40"
                >
                  {content}
                </Link>
              );
            }

            return (
              <div key={item.id} className="block">
                {content}
              </div>
            );
          })
        )}
      </div>
    </section>
  );
}

export function TenantWorkspaceDashboard() {
  const { data, isFetching, isError, refetch } = useWorkspaceDashboard();
  const dashboard = data ?? emptyWorkspaceDashboard;

  const actionSeries = useMemo(
    () =>
      dashboard.actions
        .filter((action) => action.count > 0)
        .map((action, index) => ({
          key: action.id,
          label: action.label,
          value: action.count,
          fill: chartColorAt(index),
        })),
    [dashboard.actions],
  );

  const attentionSeries = useMemo(
    () =>
      kpiSeries(dashboard.kpis, [
        "unread_notifications",
        "ea_awaiting_my_approval",
        "ea_stale_approvals",
        "rollout_gates_awaiting_me",
        "ticketing_assigned_me",
        "rollout_sla_risk",
      ]).filter((row) => row.value > 0),
    [dashboard.kpis],
  );

  return (
    <div className="flex flex-col gap-6">
      <LiveProductTourHost />
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Dashboard</h1>
          <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
            Work awaiting you across modules — gate approvals, e-approvals, tickets, and SLA risk.
          </p>
          {dashboard.quick_links.length > 0 ? (
            <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-medium">
              {dashboard.quick_links.map((link) => (
                <Link
                  key={link.href}
                  href={link.href}
                  className="text-primary underline-offset-4 hover:underline"
                >
                  {link.label}
                </Link>
              ))}
            </p>
          ) : null}
        </div>
        <Button size="sm" variant="outline" onClick={() => void refetch()} disabled={isFetching}>
          {isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
          Refresh
        </Button>
      </header>

      <KpiStrip items={dashboard.kpis} />

      <AwaitingMeHub
        total={dashboard.awaiting_me?.total ?? 0}
        items={dashboard.awaiting_me?.items ?? []}
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <DashboardBarChart
          title="Action queues"
          description="Items in your operational queues"
          data={actionSeries}
          layout="horizontal"
          emptyMessage="No queued actions right now."
          height={200}
        />
        <DashboardDonutChart
          title="Attention mix"
          description="Notifications, approvals, tickets, and SLA risk"
          data={attentionSeries}
          emptyMessage="Nothing requiring attention."
          height={200}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <ActionableWidgets items={dashboard.actions} />
        <RecentActivityPanel items={dashboard.recent_activity} />
      </div>

      {isError ? (
        <Card className={cn("border-destructive/40 bg-destructive/5")}>
          <CardHeader className="pb-2">
            <CardTitle className="text-base font-medium text-destructive">
              Unable to load dashboard
            </CardTitle>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground">
            Check API connectivity and try refresh. Module-specific dashboards remain available from
            the sidebar.
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
