"use client";

import dynamic from "next/dynamic";
import type { ComponentType } from "react";

import { DashboardChartSkeleton } from "@/components/dashboard/dashboard-chart-skeleton";
import type {
  DashboardBubbleChartProps,
  DashboardMultiLineChartProps,
  DashboardPolarChartProps,
  DashboardRadarChartProps,
  DashboardScatterChartProps,
  DashboardStackedAreaChartProps,
} from "@/components/dashboard/dashboard-extended-charts-impl";

const loading = () => <DashboardChartSkeleton />;

export const DashboardMultiLineChart = dynamic(
  () =>
    import("@/components/dashboard/dashboard-extended-charts-impl").then(
      (m) => m.DashboardMultiLineChartImpl,
    ),
  { ssr: false, loading },
) as ComponentType<DashboardMultiLineChartProps>;

export const DashboardStackedAreaChart = dynamic(
  () =>
    import("@/components/dashboard/dashboard-extended-charts-impl").then(
      (m) => m.DashboardStackedAreaChartImpl,
    ),
  { ssr: false, loading },
) as ComponentType<DashboardStackedAreaChartProps>;

export const DashboardRadarChart = dynamic(
  () =>
    import("@/components/dashboard/dashboard-extended-charts-impl").then(
      (m) => m.DashboardRadarChartImpl,
    ),
  { ssr: false, loading },
) as ComponentType<DashboardRadarChartProps>;

export const DashboardPolarChart = dynamic(
  () =>
    import("@/components/dashboard/dashboard-extended-charts-impl").then(
      (m) => m.DashboardPolarChartImpl,
    ),
  { ssr: false, loading },
) as ComponentType<DashboardPolarChartProps>;

export const DashboardBubbleChart = dynamic(
  () =>
    import("@/components/dashboard/dashboard-extended-charts-impl").then(
      (m) => m.DashboardBubbleChartImpl,
    ),
  { ssr: false, loading },
) as ComponentType<DashboardBubbleChartProps>;

export const DashboardScatterChart = dynamic(
  () =>
    import("@/components/dashboard/dashboard-extended-charts-impl").then(
      (m) => m.DashboardScatterChartImpl,
    ),
  { ssr: false, loading },
) as ComponentType<DashboardScatterChartProps>;
