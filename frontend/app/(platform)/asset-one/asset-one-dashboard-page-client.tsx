"use client";

import Link from "next/link";
import { useMemo } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { kpiSeries } from "@/components/dashboard/dashboard-chart-utils";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import { DashboardContentSkeleton } from "@/components/ui/page-skeletons";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { useAssetOneDashboard } from "@/hooks/use-asset-one-dashboard";
import { permissions } from "@/lib/rbac/permissions";

export function AssetOneDashboardPageClient() {
  const { data, isFetching, isError, isPlaceholderData, refetch } = useAssetOneDashboard();
  const showSkeleton = isFetching && isPlaceholderData;

  const categorySeries = useMemo(
    () =>
      (data?.by_category ?? []).map((row) => ({
        key: row.category,
        label: row.category,
        value: row.count,
      })),
    [data?.by_category],
  );

  const lifecycleSeries = useMemo(
    () =>
      kpiSeries(data?.kpis ?? [], ["assets_wh", "assets_dep", "assets_transit"]).filter(
        (row) => row.value > 0,
      ),
    [data?.kpis],
  );

  return (
    <PermissionGate requiredPermissions={[permissions.assetOneView]}>
      <div className="space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">ASSET-ONE</h1>
            <p className="mt-1 max-w-2xl text-sm leading-relaxed text-muted-foreground">
              Warehouse and field asset registry—RFID-ready fields, lifecycle status, and category rollups per board
              scope. Browse the{" "}
              <Link className="text-primary underline-offset-4 hover:underline" href="/asset-one/assets">
                full asset directory
              </Link>
              .
            </p>
          </div>
          <Button size="sm" variant="outline" type="button" onClick={() => refetch()} disabled={isFetching}>
            {isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
            Refresh
          </Button>
        </header>

        {isError ? (
          <p className="text-sm text-destructive">Could not load ASSET-ONE dashboard.</p>
        ) : null}

        {showSkeleton ? <DashboardContentSkeleton /> : null}

        {!showSkeleton ? (
        <>
        <KpiStrip items={data?.kpis ?? []} />

        <div className="grid gap-4 lg:grid-cols-2">
          <DashboardBarChart
            title="Assets by category"
            description="Top categories in the registry"
            data={categorySeries}
            layout="horizontal"
            emptyMessage="No category rollups yet."
            height={220}
          />
          <DashboardDonutChart
            title="Lifecycle mix"
            description="Warehouse, deployed, and in transit"
            data={lifecycleSeries}
            emptyMessage="No lifecycle counts yet."
            height={220}
          />
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div className="border-b border-border px-4 py-3">
              <h2 className="text-sm font-medium text-foreground">By category</h2>
            </div>
            <ul className="divide-y divide-border p-2 text-sm">
              {(data?.by_category ?? []).length === 0 ? (
                <li className="px-2 py-4 text-center text-muted-foreground">No assets yet.</li>
              ) : (
                (data?.by_category ?? []).map((row) => (
                  <li key={row.category} className="flex items-center justify-between px-2 py-2">
                    <span className="text-foreground">{row.category}</span>
                    <span className="font-mono text-xs text-muted-foreground">{row.count}</span>
                  </li>
                ))
              )}
            </ul>
          </div>

          <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div className="border-b border-border px-4 py-3">
              <h2 className="text-sm font-medium text-foreground">Recent assets</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-border bg-muted/40">
                  <tr>
                    <th className="px-3 py-2 text-[13px] font-medium text-muted-foreground">Code</th>
                    <th className="px-3 py-2 text-[13px] font-medium text-muted-foreground">Name</th>
                    <th className="px-3 py-2 text-[13px] font-medium text-muted-foreground">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {(data?.assets ?? []).length === 0 ? (
                    <tr>
                      <td colSpan={3} className="px-3 py-4 text-center text-muted-foreground">
                        No rows yet.
                      </td>
                    </tr>
                  ) : (
                    (data?.assets ?? []).map((a) => (
                      <tr key={a.id} className="border-t border-border">
                        <td className="px-3 py-2 font-mono text-xs text-muted-foreground">{a.asset_code}</td>
                        <td className="px-3 py-2 text-foreground">{a.name}</td>
                        <td className="px-3 py-2 text-muted-foreground">{a.status}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
        </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
