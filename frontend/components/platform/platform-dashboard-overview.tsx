"use client";

import Link from "next/link";
import { useMemo } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { recordToSeries, DASHBOARD_CHART } from "@/components/dashboard/dashboard-chart-utils";
import { ActionableWidgets } from "@/components/project-one/actionable-widgets";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import { PlatformProvisioningChart } from "@/components/platform/platform-provisioning-chart";
import { environmentBadgeClass } from "@/components/platform/tenant-environment-sheet";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { usePlatformDashboard, platformDashboardEmptyState } from "@/hooks/use-platform-dashboard";
import { formatAuditChangeSummary } from "@/lib/platform/platform-audit-utils";
import { cn } from "@/lib/utils";

function formatCreatedAt(iso: string | null | undefined): string {
  if (!iso) {
    return "—";
  }
  try {
    return new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(new Date(iso));
  } catch {
    return iso;
  }
}

function subscriptionTone(status: string): string {
  const normalized = status.toLowerCase();
  if (normalized === "active") {
    return "text-emerald-600 dark:text-emerald-400";
  }
  if (normalized === "past_due" || normalized === "trial") {
    return "text-amber-600 dark:text-amber-400";
  }

  return "text-red-600 dark:text-red-400";
}

type Props = {
  enabled: boolean;
  beforeRecentActivity?: React.ReactNode;
};

export function PlatformDashboardOverview({ enabled, beforeRecentActivity }: Props) {
  const { data, isFetching, isError, isPlaceholderData } = usePlatformDashboard(enabled);
  const dashboard = data ?? platformDashboardEmptyState;
  const showSkeleton = !enabled || (isFetching && isPlaceholderData);

  const environmentEntries = Object.entries(dashboard.environment_breakdown).sort(([a], [b]) =>
    a.localeCompare(b),
  );
  const brandEntries = Object.entries(dashboard.brand_breakdown).slice(0, 8);

  const planSeries = useMemo(
    () => recordToSeries(dashboard.plan_breakdown ?? {}, (key) => key.charAt(0).toUpperCase() + key.slice(1)),
    [dashboard.plan_breakdown],
  );
  const subscriptionSeries = useMemo(
    () =>
      recordToSeries(dashboard.subscription_breakdown ?? {}, (key) =>
        key.replace(/_/g, " "),
      ),
    [dashboard.subscription_breakdown],
  );
  const seatUtilSeries = useMemo(
    () =>
      [...dashboard.seat_usage]
        .map((row) => ({
          key: row.id,
          label: row.label,
          value: row.utilization_percent,
          fill: row.over_limit
            ? DASHBOARD_CHART.danger
            : row.utilization_percent >= 80
              ? DASHBOARD_CHART.warning
              : DASHBOARD_CHART.brand,
        }))
        .sort((a, b) => b.value - a.value)
        .slice(0, 8),
    [dashboard.seat_usage],
  );

  return (
    <section className="space-y-4">
      <div>
        <h2 className="text-base font-medium text-foreground">Tenant statistics</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Central registry — organizations, seat usage, database health, subscriptions, and provisioning
          activity.
        </p>
      </div>

      {showSkeleton ? <DashboardContentSkeleton /> : null}

      {!showSkeleton ? (
      <>
      <KpiStrip items={dashboard.kpis} />

      <div className="grid gap-4 lg:grid-cols-2">
        <PlatformProvisioningChart points={dashboard.provisioning_trend} />
        <DashboardDonutChart
          title="Plan mix"
          description="Tenants by plan tier"
          data={planSeries}
          emptyMessage="No plan breakdown available."
          height={220}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <DashboardDonutChart
          title="Subscription status"
          description="Active, trial, past due, and canceled"
          data={subscriptionSeries}
          emptyMessage="No subscription breakdown available."
          height={220}
        />
        <DashboardBarChart
          title="Seat utilization"
          description="Top tenants by paid seat usage %"
          data={seatUtilSeries}
          layout="horizontal"
          valueLabel="%"
          emptyMessage="No seat utilization data."
          height={220}
        />
      </div>

      <Card className="shadow-sm">
          <CardHeader className="border-b pb-3">
            <CardTitle className="text-base font-medium">Seat usage</CardTitle>
            <p className="text-xs font-normal text-muted-foreground">
              {dashboard.seat_summary.total_seats_used} / {dashboard.seat_summary.total_seat_limit} paid seats across
              healthy tenants
              {dashboard.seat_summary.tenants_over_limit > 0
                ? ` · ${dashboard.seat_summary.tenants_over_limit} over limit`
                : ""}
              {dashboard.seat_summary.tenants_near_limit > 0
                ? ` · ${dashboard.seat_summary.tenants_near_limit} near limit (≥80%)`
                : ""}
            </p>
          </CardHeader>
          <CardContent className="p-0">
            {dashboard.seat_usage.length === 0 ? (
              <p className="p-4 text-sm text-muted-foreground">
                No seat data — tenants may be missing databases or pending migrations.
              </p>
            ) : (
              <ul className="divide-y divide-border">
                {dashboard.seat_usage.map((row) => (
                  <li key={row.id} className="px-4 py-3 text-sm">
                    <div className="flex items-center justify-between gap-2">
                      <div className="min-w-0">
                        <p className="font-medium text-foreground">{row.label}</p>
                        <p className="truncate text-xs text-muted-foreground">
                          {row.primary_domain ?? "—"} · {row.seat_used}/{row.seat_limit} seats
                        </p>
                      </div>
                      <div className="flex shrink-0 items-center gap-1.5">
                        {row.over_limit ? (
                          <Badge variant="outline" className="border-red-300 text-red-700">
                            Over limit
                          </Badge>
                        ) : null}
                        <span
                          className={cn(
                            "text-xs font-medium tabular-nums",
                            row.utilization_percent >= 80 ? "text-amber-600" : "text-muted-foreground",
                          )}
                        >
                          {row.utilization_percent}%
                        </span>
                      </div>
                    </div>
                    <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                      <div
                        className={cn(
                          "h-full rounded-full transition-all",
                          row.over_limit
                            ? "bg-red-500"
                            : row.utilization_percent >= 80
                              ? "bg-amber-500"
                              : "bg-primary",
                        )}
                        style={{ width: `${Math.min(100, row.utilization_percent)}%` }}
                      />
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

      {environmentEntries.length > 0 || brandEntries.length > 0 ? (
        <div className="grid gap-4 lg:grid-cols-2">
          {environmentEntries.length > 0 ? (
            <Card size="sm" className="py-0 shadow-sm">
              <CardContent className="py-3">
                <p className="mb-2 text-xs font-medium text-muted-foreground">By environment</p>
                <div className="flex flex-wrap gap-2">
                  {environmentEntries.map(([env, count]) => (
                    <Badge
                      key={env}
                      variant="outline"
                      className={cn("font-normal", environmentBadgeClass(env))}
                    >
                      {env}: {count}
                    </Badge>
                  ))}
                </div>
              </CardContent>
            </Card>
          ) : null}

          {brandEntries.length > 0 ? (
            <Card size="sm" className="py-0 shadow-sm">
              <CardContent className="py-3">
                <p className="mb-2 text-xs font-medium text-muted-foreground">By brand domain</p>
                <div className="flex flex-wrap gap-2">
                  {brandEntries.map(([brand, count]) => (
                    <Badge key={brand} variant="secondary" className="font-normal">
                      <span className="font-mono">{brand}</span>: {count}
                    </Badge>
                  ))}
                </div>
              </CardContent>
            </Card>
          ) : null}
        </div>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <ActionableWidgets items={dashboard.actions} />

        <Card className="shadow-sm">
          <CardHeader className="border-b pb-3">
            <CardTitle className="text-base font-medium">Database health</CardTitle>
            <p className="text-xs font-normal text-muted-foreground">
              {dashboard.health_summary.healthy} healthy · {dashboard.health_summary.database_missing} missing DB ·{" "}
              {dashboard.health_summary.migrations_pending} pending migrations
            </p>
          </CardHeader>
          <CardContent className="p-0">
            {dashboard.health_issues.length === 0 ? (
              <p className="p-4 text-sm text-emerald-600 dark:text-emerald-400">
                All tenant databases exist and migrations are up to date.
              </p>
            ) : (
              <ul className="divide-y divide-border">
                {dashboard.health_issues.map((issue) => (
                  <li key={`${issue.id}-${issue.issue}`} className="px-4 py-3 text-sm">
                    <p className="font-medium text-foreground">
                      {issue.slug ?? issue.primary_domain ?? issue.id.slice(0, 8)}
                    </p>
                    <p className="text-xs text-muted-foreground">{issue.detail}</p>
                    <Badge variant="outline" className="mt-1.5 text-[10px]">
                      {issue.issue === "missing_database" ? "Missing DB" : "Migrate required"}
                    </Badge>
                  </li>
                ))}
              </ul>
            )}
            <p className="border-t border-border px-4 py-2 text-[11px] text-muted-foreground">
              Repair:{" "}
              <code className="rounded bg-muted px-1 py-0.5 font-mono text-[10px]">
                php artisan toweros:repair-tenant-databases --create
              </code>
            </p>
          </CardContent>
        </Card>
      </div>

      {dashboard.subscription_alerts.length > 0 ? (
        <Card className="border-amber-200/80 shadow-sm dark:border-amber-900/50">
          <CardHeader className="border-b border-amber-200/60 pb-3 dark:border-amber-900/40">
            <CardTitle className="text-base font-medium">Subscription alerts</CardTitle>
            <p className="text-xs font-normal text-muted-foreground">
              Tenants with non-active subscription status
            </p>
          </CardHeader>
          <CardContent className="p-0">
            <ul className="divide-y divide-border">
              {dashboard.subscription_alerts.map((alert) => (
                <li
                  key={alert.id}
                  className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                >
                  <div>
                    <p className="font-medium text-foreground">
                      {alert.slug ?? alert.primary_domain ?? alert.id.slice(0, 8)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {alert.plan_tier} · {alert.environment ?? "—"}
                    </p>
                  </div>
                  <span className={cn("text-xs font-medium uppercase", subscriptionTone(alert.subscription_status))}>
                    {alert.subscription_status.replace(/_/g, " ")}
                  </span>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      ) : null}

      </>
      ) : null}

      {beforeRecentActivity}

      {!showSkeleton ? (
      <>
      <Card className="shadow-sm">
        <CardHeader className="border-b pb-3">
          <CardTitle className="text-base font-medium">Recent platform activity</CardTitle>
          <p className="text-xs font-normal text-muted-foreground">
            Billing, modules, MFA, impersonation, and lifecycle events
          </p>
        </CardHeader>
        <CardContent className="p-0">
          {dashboard.recent_audit.length === 0 ? (
            <p className="p-4 text-sm text-muted-foreground">No activity recorded yet.</p>
          ) : (
            <ul className="divide-y divide-border">
              {dashboard.recent_audit.map((entry) => (
                <li key={entry.id} className="px-4 py-3 text-sm">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline" className="font-normal">
                      {entry.event_label}
                    </Badge>
                    <span className="text-xs text-muted-foreground">
                      {entry.created_at ? new Date(entry.created_at).toLocaleString() : ""}
                    </span>
                  </div>
                  <p className="mt-1 text-foreground">{formatAuditChangeSummary(entry)}</p>
                  <p className="mt-1 text-xs text-muted-foreground">
                    {entry.tenant_slug ?? entry.tenant_domain ?? entry.tenant_id?.slice(0, 8) ?? "—"} ·{" "}
                    {entry.actor_email ?? "System"}
                  </p>
                  {entry.tenant_id ? (
                    <Link
                      href={`/platform/tenants/${entry.tenant_id}`}
                      className="mt-1 inline-block text-xs font-medium text-primary underline-offset-4 hover:underline"
                    >
                      View tenant
                    </Link>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>

      <Card className="shadow-sm">
        <CardHeader className="border-b pb-3">
          <CardTitle className="text-base font-medium">Recently provisioned</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          {dashboard.recent_tenants.length === 0 ? (
            <p className="p-4 text-sm text-muted-foreground">No tenants yet.</p>
          ) : (
            <ul className="divide-y divide-border">
              {dashboard.recent_tenants.map((tenant) => (
                <li
                  key={tenant.id}
                  className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                >
                  <div className="min-w-0">
                    <p className="font-medium text-foreground">
                      {tenant.slug ?? tenant.primary_domain ?? tenant.id.slice(0, 8)}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                      {tenant.primary_domain ?? "No hostname"} · {formatCreatedAt(tenant.created_at)}
                    </p>
                  </div>
                  <div className="flex flex-wrap items-center gap-1.5">
                    {tenant.environment ? (
                      <Badge variant="outline" className={environmentBadgeClass(tenant.environment)}>
                        {tenant.environment}
                      </Badge>
                    ) : null}
                    {tenant.playbook_upgrade_available ? (
                      <Badge variant="outline" className="border-amber-300 text-amber-700">
                        Upgrade
                      </Badge>
                    ) : null}
                    {tenant.mfa_required ? <Badge variant="outline">MFA</Badge> : null}
                  </div>
                </li>
              ))}
            </ul>
          )}
          <div className="border-t border-border px-4 py-3">
            <Link
              href="/platform/tenants/create"
              className="text-xs font-medium text-primary underline-offset-4 hover:underline"
            >
              Create tenant
            </Link>
          </div>
        </CardContent>
      </Card>

      </>
      ) : null}

      {isFetching && !showSkeleton ? <RefreshingHint label="Refreshing statistics" /> : null}
      {isError ? (
        <p className="text-xs text-red-600 dark:text-red-400">
          Could not load platform dashboard statistics. The tenant directory may still be available.
        </p>
      ) : null}
    </section>
  );
}
