"use client";

import { useId, useMemo } from "react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

import {
  DASHBOARD_CHART,
  type DashboardChartDatum,
} from "@/components/dashboard/dashboard-chart-utils";
import { DashboardResponsiveChart } from "@/components/dashboard/dashboard-responsive-chart";
import { DashboardWidget } from "@/components/dashboard/dashboard-widget";

export type DashboardLineChartProps = {
  title: string;
  description?: string;
  data: DashboardChartDatum[];
  emptyMessage?: string;
  valueLabel?: string;
  height?: number;
  className?: string;
};

export function DashboardLineChartImpl({
  title,
  description,
  data,
  emptyMessage = "No data to chart yet.",
  valueLabel = "Count",
  height = 220,
  className,
}: DashboardLineChartProps) {
  const gradientId = `analytics-area-${useId().replace(/:/g, "")}`;
  const chartData = useMemo(
    () =>
      data.map((row) => ({
        ...row,
        fill: row.fill ?? DASHBOARD_CHART.brand,
      })),
    [data],
  );
  const hasData = chartData.some((row) => row.value > 0);
  const latest = chartData.length > 0 ? chartData[chartData.length - 1]!.value : 0;
  const first = chartData.length > 0 ? chartData[0]!.value : 0;
  const deltaPct =
    chartData.length > 1 && first !== 0 ? ((latest - first) / Math.abs(first)) * 100 : null;
  const delta =
    deltaPct == null
      ? null
      : `${deltaPct > 0 ? "+" : ""}${deltaPct.toFixed(1)}%`;
  const deltaTone = deltaPct == null ? "neutral" : deltaPct > 0 ? "success" : deltaPct < 0 ? "danger" : "neutral";

  return (
    <DashboardWidget
      title={title}
      description={description}
      className={className}
      contentClassName="pt-2"
      stat={hasData ? latest.toLocaleString() : undefined}
      delta={hasData ? delta : null}
      deltaTone={deltaTone}
    >
        {!hasData ? (
          <p className="flex flex-1 items-center justify-center py-8 text-center text-xs text-muted-foreground">
            {emptyMessage}
          </p>
        ) : (
          <DashboardResponsiveChart height={height}>
            <AreaChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
              <defs>
                <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor={DASHBOARD_CHART.brand} stopOpacity={0.35} />
                  <stop offset="95%" stopColor={DASHBOARD_CHART.brand} stopOpacity={0.02} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
              <XAxis
                dataKey="label"
                tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
                axisLine={false}
                tickLine={false}
                interval={chartData.length > 14 ? "preserveStartEnd" : 0}
                angle={chartData.length > 10 ? -25 : 0}
                textAnchor={chartData.length > 10 ? "end" : "middle"}
                height={chartData.length > 10 ? 48 : 28}
              />
              <YAxis
                allowDecimals={false}
                tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
                axisLine={false}
                tickLine={false}
                width={28}
              />
              <Tooltip
                contentStyle={{
                  backgroundColor: "var(--popover)",
                  border: "1px solid var(--border)",
                  borderRadius: "8px",
                  fontSize: "12px",
                }}
                formatter={(value) => [`${value}`, valueLabel]}
              />
              <Area
                type="monotone"
                dataKey="value"
                stroke={DASHBOARD_CHART.brand}
                fill={`url(#${gradientId})`}
                strokeWidth={2}
              />
            </AreaChart>
          </DashboardResponsiveChart>
        )}
    </DashboardWidget>
  );
}
