"use client";

import Link from "next/link";
import { useMemo } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { countBy, DASHBOARD_CHART, kpiSeries, parseKpiNumber } from "@/components/dashboard/dashboard-chart-utils";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { DashboardContentSkeleton, TableBlockSkeleton } from "@/components/ui/page-skeletons";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { useTowerOneDashboard } from "@/hooks/use-tower-one-dashboard";
import { permissions } from "@/lib/rbac/permissions";

export function TowerOneDashboardPageClient() {
  const { data, isFetching, isError, isPlaceholderData, refetch } = useTowerOneDashboard();
  const showSkeleton = isFetching && isPlaceholderData;

  const statusSeries = useMemo(() => {
    const kpis = data?.kpis ?? [];
    const ops = parseKpiNumber(kpis.find((k) => k.key === "towers_ops")?.value);
    const maint = parseKpiNumber(kpis.find((k) => k.key === "towers_maint")?.value);
    const total = parseKpiNumber(kpis.find((k) => k.key === "towers_total")?.value);
    const other = Math.max(0, total - ops - maint);
    return [
      { key: "ops", label: "Operational", value: ops, fill: DASHBOARD_CHART.success },
      { key: "maint", label: "Maintenance", value: maint, fill: DASHBOARD_CHART.warning },
      { key: "other", label: "Other", value: other, fill: DASHBOARD_CHART.muted },
    ].filter((row) => row.value > 0);
  }, [data?.kpis]);

  const typeSeries = useMemo(
    () => countBy(data?.towers ?? [], (row) => row.tower_type || "Unknown"),
    [data?.towers],
  );

  return (
    <PermissionGate requiredPermissions={[permissions.towerOneView]}>
      <div className="space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">TOWER-ONE</h1>
            <p className="mt-1 max-w-2xl text-sm leading-relaxed text-muted-foreground">
              Tower inventory, structural attributes, and co-location capacity per the board module scope. Open the{" "}
              <Link className="text-primary underline-offset-4 hover:underline" href="/tower-one/towers">
                full tower directory
              </Link>{" "}
              for search and pagination, or review{" "}
              <Link className="text-primary underline-offset-4 hover:underline" href="/sites">
                sites
              </Link>{" "}
              first.
            </p>
          </div>
          <Button size="sm" variant="outline" type="button" onClick={() => refetch()} disabled={isFetching}>
            {isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
            Refresh
          </Button>
        </header>

        {isError ? (
          <p className="text-sm text-destructive">Could not load TOWER-ONE dashboard.</p>
        ) : null}

        {showSkeleton ? (
          <>
            <DashboardContentSkeleton />
            <TableBlockSkeleton rows={5} />
          </>
        ) : null}

        {!showSkeleton ? (
        <>
        <KpiStrip items={data?.kpis ?? []} />

        <div className="grid gap-4 lg:grid-cols-2">
          <DashboardDonutChart
            title="Tower status"
            description="Operational vs maintenance mix"
            data={statusSeries.length > 0 ? statusSeries : kpiSeries(data?.kpis ?? [], ["towers_ops", "towers_maint"])}
            emptyMessage="No tower status data yet."
            height={200}
          />
          <DashboardBarChart
            title="Recent by type"
            description="Type mix of recently listed towers"
            data={typeSeries}
            emptyMessage="No recent towers to chart."
            height={200}
          />
        </div>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <div className="border-b border-border px-4 py-3">
            <h2 className="text-sm font-medium text-foreground">Recent towers</h2>
            <p className="text-xs text-muted-foreground">Linked to sites; data from tenant database.</p>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-left text-sm">
              <thead className="border-b border-border bg-muted/40">
                <tr>
                  <th className="px-4 py-2 text-[13px] font-medium text-muted-foreground">Site</th>
                  <th className="px-4 py-2 text-[13px] font-medium text-muted-foreground">Code</th>
                  <th className="px-4 py-2 text-[13px] font-medium text-muted-foreground">Type</th>
                  <th className="px-4 py-2 text-[13px] font-medium text-muted-foreground">Status</th>
                  <th className="px-4 py-2 text-[13px] font-medium text-muted-foreground">Height (m)</th>
                </tr>
              </thead>
              <tbody>
                {(data?.towers ?? []).length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-6 text-center text-muted-foreground">
                      No towers yet. Add sites, then register towers against those sites.
                    </td>
                  </tr>
                ) : (
                  (data?.towers ?? []).map((row) => (
                    <tr key={row.id} className="border-t border-border">
                      <td className="px-4 py-2 text-foreground">{row.site}</td>
                      <td className="px-4 py-2 font-mono text-xs text-muted-foreground">{row.site_code || "—"}</td>
                      <td className="px-4 py-2 text-muted-foreground">{row.tower_type}</td>
                      <td className="px-4 py-2 text-muted-foreground">{row.status}</td>
                      <td className="px-4 py-2 text-muted-foreground">{row.height_m ?? "—"}</td>
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
