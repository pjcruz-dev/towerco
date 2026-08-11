"use client";

import dynamic from "next/dynamic";
import type { ComponentType } from "react";

import { DashboardChartSkeleton } from "@/components/dashboard/dashboard-chart-skeleton";
import type { DashboardBarChartProps } from "@/components/dashboard/dashboard-bar-chart-impl";

/** Lazy wrapper: keeps recharts out of the initial bundle until a bar chart renders. */
export const DashboardBarChart = dynamic(
  () => import("@/components/dashboard/dashboard-bar-chart-impl").then((m) => m.DashboardBarChartImpl),
  {
    ssr: false,
    loading: () => <DashboardChartSkeleton />,
  },
) as ComponentType<DashboardBarChartProps>;
