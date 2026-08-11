"use client";

import Link from "next/link";
import { useMemo } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { DASHBOARD_CHART, parseKpiNumber } from "@/components/dashboard/dashboard-chart-utils";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { useFiberOneDashboard } from "@/hooks/use-fiber-one-dashboard";
import { permissions } from "@/lib/rbac/permissions";

export function FiberOneDashboardPageClient() {
  const { data, isFetching, isError, isPlaceholderData, refetch } = useFiberOneDashboard();
  const showSkeleton = isFetching && isPlaceholderData;

  const statusSeries = useMemo(() => {
    const kpis = data?.kpis ?? [];
    const active = parseKpiNumber(kpis.find((k) => k.key === "fiber_active")?.value);
    const planned = parseKpiNumber(kpis.find((k) => k.key === "fiber_planned")?.value);
    const total = parseKpiNumber(kpis.find((k) => k.key === "fiber_routes")?.value);
    const other = Math.max(0, total - active - planned);
    return [
      { key: "active", label: "Active", value: active, fill: DASHBOARD_CHART.success },
      { key: "planned", label: "Planned", value: planned, fill: DASHBOARD_CHART.brand },
      { key: "other", label: "Other", value: other, fill: DASHBOARD_CHART.muted },
    ].filter((row) => row.value > 0);
  }, [data?.kpis]);

  const lengthSeries = useMemo(
    () =>
      [...(data?.routes ?? [])]
        .map((route) => ({
          key: route.id,
          label: route.name,
          value: parseKpiNumber(route.length_km),
        }))
        .filter((row) => row.value > 0)
        .sort((a, b) => b.value - a.value)
        .slice(0, 8),
    [data?.routes],
  );

  return (
    <PermissionGate requiredPermissions={[permissions.fiberOneView]}>
      <div className="space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">FIBER-ONE</h1>
            <p className="mt-1 max-w-2xl text-sm leading-relaxed text-muted-foreground">
              Fiber route registry between sites—foundation for topology GIS and cross-connect workflows. Open the{" "}
              <Link className="text-primary underline-offset-4 hover:underline" href="/fiber-one/routes">
                route directory
              </Link>
              , review{" "}
              <Link className="text-primary underline-offset-4 hover:underline" href="/sites">
                sites
              </Link>
              , or use{" "}
              <Link className="text-primary underline-offset-4 hover:underline" href="/gis">
                GIS network view
              </Link>{" "}
              for map operations.
            </p>
          </div>
          <Button size="sm" variant="outline" type="button" onClick={() => refetch()} disabled={isFetching}>
            {isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
            Refresh
          </Button>
        </header>

        {isError ? (
          <p className="text-sm text-destructive">Could not load FIBER-ONE dashboard.</p>
        ) : null}

        {showSkeleton ? <DashboardContentSkeleton /> : null}

        {!showSkeleton ? (
        <>
        <KpiStrip items={data?.kpis ?? []} />

        <div className="grid gap-4 lg:grid-cols-2">
          <DashboardDonutChart
            title="Route status"
            description="Active vs planned fiber routes"
            data={statusSeries}
            emptyMessage="No route status data yet."
            height={200}
          />
          <DashboardBarChart
            title="Longest routes"
            description="Top routes by length (km)"
            data={lengthSeries}
            layout="horizontal"
            valueLabel="km"
            emptyMessage="No route lengths to chart."
            height={200}
          />
        </div>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <div className="border-b border-border px-4 py-3">
            <h2 className="text-sm font-medium text-foreground">Routes</h2>
            <p className="text-xs text-muted-foreground">Logical spans; extend for OTDR traces and splice assets.</p>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-left text-sm">
              <thead className="border-b border-border bg-muted/40">
                <tr>
                  <th className="px-4 py-2 text-[13px] font-medium text-muted-foreground">Name</th>
                  <th className="px-4 py-2 text-[13px] font-medium text-muted-foreground">Status</th>
                  <th className="px-4 py-2 text-[13px] font-medium text-muted-foreground">From site</th>
                  <th className="px-4 py-2 text-[13px] font-medium text-muted-foreground">To site</th>
                  <th className="px-4 py-2 text-[13px] font-medium text-muted-foreground">Length (km)</th>
                </tr>
              </thead>
              <tbody>
                {(data?.routes ?? []).length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-6 text-center text-muted-foreground">
                      No fiber routes yet.
                    </td>
                  </tr>
                ) : (
                  (data?.routes ?? []).map((row) => (
                    <tr key={row.id} className="border-t border-border">
                      <td className="px-4 py-2 text-foreground">{row.name}</td>
                      <td className="px-4 py-2 text-muted-foreground">{row.status}</td>
                      <td className="px-4 py-2 text-muted-foreground">{row.from ?? "—"}</td>
                      <td className="px-4 py-2 text-muted-foreground">{row.to ?? "—"}</td>
                      <td className="px-4 py-2 text-muted-foreground">{row.length_km ?? "—"}</td>
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
