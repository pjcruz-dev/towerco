"use client";

import Link from "next/link";
import { useMemo } from "react";
import { ArrowRight, LifeBuoy, Plus, Ticket } from "lucide-react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { countBy, DASHBOARD_CHART, kpiSeries } from "@/components/dashboard/dashboard-chart-utils";
import { TicketingPriorityBadge, TicketingStatusBadge } from "@/components/ticketing/ticketing-badges";
import { TicketingPageHeader } from "@/components/ticketing/ticketing-page-header";
import { formatTicketingDate } from "@/components/ticketing/ticketing-utils";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { useTicketingDashboard } from "@/hooks/use-ticketing-dashboard";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

const NAV_TILES = [
  {
    href: "/ticketing/tickets",
    label: "All tickets",
    description: "Search, filter, and manage the queue.",
    icon: Ticket,
  },
  {
    href: "/ticketing/tickets/new",
    label: "Report an issue",
    description: "Describe the problem and attach screenshots.",
    icon: LifeBuoy,
  },
] as const;

export function TicketingDashboardPageClient() {
  const { data, isFetching, isError, error, isPlaceholderData, refetch } = useTicketingDashboard();
  const showSkeleton = isFetching && isPlaceholderData;

  const queueSeries = useMemo(
    () =>
      kpiSeries(data?.kpis ?? [], ["open", "assigned_me", "urgent", "sla_at_risk", "resolved_week"]).filter(
        (row) => row.value > 0,
      ),
    [data?.kpis],
  );

  const prioritySeries = useMemo(
    () =>
      countBy(
        data?.recent_tickets ?? [],
        (ticket) => ticket.priority,
        (key) => key.charAt(0).toUpperCase() + key.slice(1),
      ).map((row) => ({
        ...row,
        fill:
          row.key === "urgent"
            ? DASHBOARD_CHART.danger
            : row.key === "high"
              ? DASHBOARD_CHART.warning
              : row.key === "normal"
                ? DASHBOARD_CHART.brand
                : DASHBOARD_CHART.muted,
      })),
    [data?.recent_tickets],
  );

  const categorySeries = useMemo(
    () =>
      (data?.by_category ?? [])
        .map((row) => ({
          key: row.category ?? "uncategorized",
          label: row.label,
          value: row.open + row.in_progress + row.resolved_7d,
          fill: DASHBOARD_CHART.brand,
        }))
        .filter((row) => row.value > 0)
        .slice(0, 8),
    [data?.by_category],
  );

  return (
    <PermissionGate requiredPermissions={[permissions.ticketingView]}>
      <div className="space-y-6">
        <TicketingPageHeader
          title="Ticketing"
          description={
            data?.message ??
            "Cross-module issue tracking — raise tickets from any TowerOS module or manually."
          }
          actions={
            <>
              <Button size="sm" variant="outline" type="button" onClick={() => refetch()} disabled={isFetching}>
                {isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
                Refresh
              </Button>
              <Button size="sm" render={<Link href="/ticketing/tickets/new" />}>
                <Plus className="mr-1.5 h-4 w-4" aria-hidden />
                New ticket
              </Button>
            </>
          }
        />

        {showSkeleton ? <DashboardContentSkeleton /> : null}
        {isError ? (
          <p className="text-sm text-destructive">
            Could not load ticketing dashboard. {getErrorMessage(error)}
          </p>
        ) : null}

        {!showSkeleton ? (
          <>
            <KpiStrip items={data?.kpis ?? []} />

            <div className="grid gap-4 lg:grid-cols-2">
              <DashboardBarChart
                title="Ticket queue"
                description="Open, assigned, urgent, and resolved this week"
                data={queueSeries}
                layout="horizontal"
                emptyMessage="No ticket KPIs to chart."
                height={200}
              />
              <DashboardDonutChart
                title="Recent by priority"
                description="Priority mix of the latest tickets shown below"
                data={prioritySeries}
                emptyMessage="No recent tickets to chart."
                height={200}
              />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
              <DashboardBarChart
                title="Volume by category"
                description="Open, in progress, and resolved (7d) across categories"
                data={categorySeries}
                layout="horizontal"
                emptyMessage="No category volume yet."
                height={240}
              />
              <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div className="border-b border-border px-4 py-3">
                  <h2 className="text-sm font-medium text-foreground">Category analytics</h2>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    Active queue and recent resolutions by category
                  </p>
                </div>
                {(data?.by_category ?? []).length === 0 ? (
                  <p className="px-4 py-8 text-center text-xs text-muted-foreground">
                    No category data yet.
                  </p>
                ) : (
                  <div className="max-h-[240px] overflow-auto">
                    <table className="w-full text-left text-[13px]">
                      <thead className="sticky top-0 border-b border-border bg-muted/80 text-xs font-medium text-muted-foreground">
                        <tr>
                          <th className="px-4 py-2">Category</th>
                          <th className="px-3 py-2 text-right">Open</th>
                          <th className="px-3 py-2 text-right">SLA risk</th>
                          <th className="px-4 py-2 text-right">Avg resolve</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(data?.by_category ?? []).map((row) => (
                          <tr key={row.category ?? "uncategorized"} className="border-b border-border last:border-0">
                            <td className="px-4 py-2 text-foreground">{row.label}</td>
                            <td className="px-3 py-2 text-right text-muted-foreground">
                              {row.open + row.in_progress}
                            </td>
                            <td className="px-3 py-2 text-right text-muted-foreground">{row.sla_at_risk}</td>
                            <td className="px-4 py-2 text-right text-muted-foreground">
                              {row.avg_resolve_hours == null ? "—" : `${row.avg_resolve_hours}h`}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              {NAV_TILES.map((tile) => {
                const Icon = tile.icon;
                return (
                  <Link
                    key={tile.href}
                    href={tile.href}
                    className={cn(
                      "group flex flex-col rounded-xl border border-border bg-card p-4 shadow-sm transition-colors",
                      "hover:border-primary/30 hover:bg-muted/30",
                    )}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon className="h-4 w-4" aria-hidden />
                      </div>
                      <ArrowRight className="h-4 w-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                    </div>
                    <p className="mt-3 text-sm font-medium text-foreground">{tile.label}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">{tile.description}</p>
                  </Link>
                );
              })}
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
              <div className="flex items-center justify-between border-b border-border px-4 py-3">
                <h2 className="text-sm font-medium text-foreground">Recent tickets</h2>
                <Link href="/ticketing/tickets" className="text-xs text-primary hover:underline">
                  View all
                </Link>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="border-b border-border bg-muted/40">
                    <tr>
                      <th className="px-4 py-2.5 text-[13px] font-medium text-muted-foreground">Ticket</th>
                      <th className="px-4 py-2.5 text-[13px] font-medium text-muted-foreground">Title</th>
                      <th className="px-4 py-2.5 text-[13px] font-medium text-muted-foreground">Status</th>
                      <th className="px-4 py-2.5 text-[13px] font-medium text-muted-foreground">Priority</th>
                      <th className="hidden px-4 py-2.5 text-[13px] font-medium text-muted-foreground md:table-cell">
                        Updated
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {(data?.recent_tickets ?? []).length === 0 ? (
                      <tr>
                        <td colSpan={5} className="px-4 py-8 text-center text-sm text-muted-foreground">
                          No tickets yet.{" "}
                          <Link href="/ticketing/tickets/new" className="text-primary hover:underline">
                            Create the first ticket
                          </Link>
                        </td>
                      </tr>
                    ) : (
                      (data?.recent_tickets ?? []).map((ticket) => (
                        <tr key={ticket.id} className="border-b border-border last:border-0 hover:bg-muted/20">
                          <td className="px-4 py-2.5 font-mono text-xs">
                            <Link href={`/ticketing/tickets/${ticket.id}`} className="text-primary hover:underline">
                              {ticket.ticket_number}
                            </Link>
                          </td>
                          <td className="max-w-xs truncate px-4 py-2.5 text-foreground">{ticket.title}</td>
                          <td className="px-4 py-2.5">
                            <TicketingStatusBadge status={ticket.status} />
                          </td>
                          <td className="px-4 py-2.5">
                            <TicketingPriorityBadge priority={ticket.priority} />
                          </td>
                          <td className="hidden px-4 py-2.5 text-xs text-muted-foreground md:table-cell">
                            {formatTicketingDate(ticket.updated_at)}
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
