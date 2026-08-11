"use client";

import dynamic from "next/dynamic";
import type { ComponentType } from "react";

import { DashboardChartSkeleton } from "@/components/dashboard/dashboard-chart-skeleton";
import type { DashboardLineChartProps } from "@/components/dashboard/dashboard-line-chart-impl";

/** Lazy wrapper: keeps recharts out of the initial bundle until a line/area chart renders. */
export const DashboardLineChart = dynamic(
  () => import("@/components/dashboard/dashboard-line-chart-impl").then((m) => m.DashboardLineChartImpl),
  {
    ssr: false,
    loading: () => <DashboardChartSkeleton />,
  },
) as ComponentType<DashboardLineChartProps>;
