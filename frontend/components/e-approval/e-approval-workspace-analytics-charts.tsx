"use client";

import { useMemo } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DASHBOARD_CHART } from "@/components/dashboard/dashboard-chart-utils";
import { EApprovalWorkspaceStatusChart } from "@/components/e-approval/e-approval-workspace-status-chart";
import type { EApprovalFormWorkspaceDashboard } from "@/modules/e-approval/form-workspace-types";

type StatusProps = {
  statusItems: EApprovalFormWorkspaceDashboard["status_breakdown"];
};

type SubsidiaryProps = {
  subsidiaryItems: NonNullable<EApprovalFormWorkspaceDashboard["subsidiary_breakdown"]>;
};

/** @deprecated Prefer separate Status / Subsidiary widgets. Kept for legacy prefs. */
export type WorkspaceChartMode = "bars" | "donut" | "both";

export function EApprovalWorkspaceStatusBreakdownChart({ statusItems }: StatusProps) {
  return <EApprovalWorkspaceStatusChart items={statusItems} />;
}

export function EApprovalWorkspaceSubsidiaryChart({ subsidiaryItems }: SubsidiaryProps) {
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

  return (
    <DashboardBarChart
      title="By subsidiary"
      description="Submission volume keyed from the subsidiary field"
      data={subsidiarySeries}
      layout="horizontal"
      emptyMessage="No subsidiary values found for the current filters."
      height={Math.min(280, 120 + subsidiarySeries.length * 28)}
    />
  );
}

/** @deprecated Bundle kept only for unexpected legacy renders. */
export function EApprovalWorkspaceAnalyticsCharts({
  statusItems,
  subsidiaryItems,
}: StatusProps & SubsidiaryProps) {
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <EApprovalWorkspaceStatusBreakdownChart statusItems={statusItems} />
      <EApprovalWorkspaceSubsidiaryChart subsidiaryItems={subsidiaryItems} />
    </div>
  );
}
