"use client";

import { useMemo } from "react";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import type { PlatformProvisioningTrendPoint } from "@/modules/platform/types";

type Props = {
  points: PlatformProvisioningTrendPoint[];
};

export function PlatformProvisioningChart({ points }: Props) {
  const data = useMemo(
    () =>
      points.map((point) => ({
        key: point.week_start,
        label: point.label,
        value: point.count,
      })),
    [points],
  );

  return (
    <DashboardBarChart
      title="Provisioning trend"
      description="New tenant rows per week (last 12 weeks)"
      data={data}
      emptyMessage="No provisioning activity in this window."
      valueLabel="Provisioned"
    />
  );
}
