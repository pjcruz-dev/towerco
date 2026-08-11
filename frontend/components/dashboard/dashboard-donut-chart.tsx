"use client";

import dynamic from "next/dynamic";
import type { ComponentType } from "react";

import { DashboardChartSkeleton } from "@/components/dashboard/dashboard-chart-skeleton";
import type { DashboardDonutChartProps } from "@/components/dashboard/dashboard-donut-chart-impl";

/** Lazy wrapper: keeps recharts out of the initial bundle until a donut chart renders. */
export const DashboardDonutChart = dynamic(
  () => import("@/components/dashboard/dashboard-donut-chart-impl").then((m) => m.DashboardDonutChartImpl),
  {
    ssr: false,
    loading: () => <DashboardChartSkeleton />,
  },
) as ComponentType<DashboardDonutChartProps>;
