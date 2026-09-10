"use client";

import { useMemo } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import { DASHBOARD_CHART } from "@/components/dashboard/dashboard-chart-utils";
import { EApprovalWorkspaceStatusChart } from "@/components/e-approval/e-approval-workspace-status-chart";
import { Button } from "@/components/ui/button";
import type { EApprovalFormWorkspaceDashboard } from "@/modules/e-approval/form-workspace-types";
import { cn } from "@/lib/utils";

export type WorkspaceChartMode = "bars" | "donut" | "both";

type Props = {
  statusItems: EApprovalFormWorkspaceDashboard["status_breakdown"];
  subsidiaryItems: NonNullable<EApprovalFormWorkspaceDashboard["subsidiary_breakdown"]>;
  mode: WorkspaceChartMode;
  onModeChange: (mode: WorkspaceChartMode) => void;
};

const STATUS_FILL: Record<string, string> = {
  pending: DASHBOARD_CHART.warning,
  returned: "#EA580C",
  approved: DASHBOARD_CHART.success,
  rejected: DASHBOARD_CHART.danger,
  cancelled: DASHBOARD_CHART.muted,
  draft: DASHBOARD_CHART.sky,
};

const MODES: Array<{ id: WorkspaceChartMode; label: string }> = [
  { id: "bars", label: "Bars" },
  { id: "donut", label: "Donut" },
  { id: "both", label: "Both" },
];

export function EApprovalWorkspaceAnalyticsCharts({
  statusItems,
  subsidiaryItems,
  mode,
  onModeChange,
}: Props) {
  const statusSeries = useMemo(
    () =>
      statusItems.map((item) => ({
        key: item.status,
        label: item.label,
        value: item.count,
        fill: STATUS_FILL[item.status] ?? DASHBOARD_CHART.brand,
      })),
    [statusItems],
  );

  const subsidiarySeries = useMemo(
    () =>
      subsidiaryItems.map((item, index) => ({
        key: item.key,
        label: item.label,
        value: item.count,
        fill: [DASHBOARD_CHART.brand, DASHBOARD_CHART.brandSoft, DASHBOARD_CHART.sky, DASHBOARD_CHART.muted][
          index % 4
        ],
      })),
    [subsidiaryItems],
  );

  const showBars = mode === "bars" || mode === "both";
  const showDonut = mode === "donut" || mode === "both";

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <p className="text-base font-medium text-foreground">Analytics</p>
          <p className="text-xs text-muted-foreground">Status mix and volume by subsidiary for the current filters.</p>
        </div>
        <div className="flex flex-wrap gap-1 rounded-lg border border-border bg-muted/40 p-0.5">
          {MODES.map((item) => (
            <Button
              key={item.id}
              type="button"
              size="sm"
              variant={mode === item.id ? "secondary" : "ghost"}
              className={cn("h-7 px-2.5 text-xs", mode === item.id && "shadow-sm")}
              onClick={() => onModeChange(item.id)}
            >
              {item.label}
            </Button>
          ))}
        </div>
      </div>

      <div className={cn("grid gap-4", mode === "both" ? "lg:grid-cols-2" : "grid-cols-1")}>
        {showBars ? (
          mode === "bars" ? (
            <EApprovalWorkspaceStatusChart items={statusItems} />
          ) : (
            <DashboardBarChart
              title="Status breakdown"
              description="Counts by workflow status"
              data={statusSeries}
              layout="horizontal"
              emptyMessage="No submissions in your current scope yet."
              height={220}
            />
          )
        ) : null}
        {showDonut ? (
          <DashboardDonutChart
            title="Status mix"
            description="Share of submissions by status"
            data={statusSeries}
            emptyMessage="No submissions in your current scope yet."
            height={220}
          />
        ) : null}
      </div>

      <DashboardBarChart
        title="By subsidiary"
        description="Submission volume keyed from the subsidiary field"
        data={subsidiarySeries}
        layout="horizontal"
        emptyMessage="No subsidiary values found for the current filters."
        height={Math.min(280, 120 + subsidiarySeries.length * 28)}
      />
    </div>
  );
}
