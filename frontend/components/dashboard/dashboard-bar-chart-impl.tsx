"use client";

import { useId, useMemo } from "react";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

import {
  DASHBOARD_CHART,
  type DashboardChartDatum,
} from "@/components/dashboard/dashboard-chart-utils";
import { DashboardResponsiveChart } from "@/components/dashboard/dashboard-responsive-chart";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";

export type DashboardBarChartProps = {
  title: string;
  description?: string;
  data: DashboardChartDatum[];
  emptyMessage?: string;
  layout?: "vertical" | "horizontal";
  valueLabel?: string;
  height?: number;
  className?: string;
};

export function DashboardBarChartImpl({
  title,
  description,
  data,
  emptyMessage = "No data to chart yet.",
  layout = "vertical",
  valueLabel = "Count",
  height = 220,
  className,
}: DashboardBarChartProps) {
  const gradientId = useId().replace(/:/g, "");
  const chartData = useMemo(
    () =>
      data.map((row) => ({
        ...row,
        fill: row.fill ?? DASHBOARD_CHART.brand,
      })),
    [data],
  );
  const hasData = chartData.some((row) => row.value > 0);
  const isHorizontal = layout === "horizontal";
  const usesPerBarFill = chartData.some((row) => Boolean(row.fill));

  return (
    <Card className={cn("flex h-full flex-col overflow-hidden rounded-xl border-border shadow-sm", className)}>
      <CardHeader className="space-y-0.5 border-b border-border/80 bg-muted/20 px-4 py-3">
        <CardTitle className="text-sm font-medium text-foreground">{title}</CardTitle>
        {description ? (
          <p className="text-[11px] font-normal leading-snug text-muted-foreground">{description}</p>
        ) : null}
      </CardHeader>
      <CardContent className="flex flex-1 flex-col p-4 pt-4">
        {!hasData ? (
          <p className="flex flex-1 items-center justify-center py-8 text-center text-xs text-muted-foreground">
            {emptyMessage}
          </p>
        ) : (
          <DashboardResponsiveChart height={height}>
            <BarChart
              data={chartData}
              layout={isHorizontal ? "vertical" : "horizontal"}
              margin={{ top: 8, right: 8, left: isHorizontal ? 8 : 0, bottom: 0 }}
            >
              <defs>
                <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor={DASHBOARD_CHART.brand} stopOpacity={0.9} />
                  <stop offset="95%" stopColor={DASHBOARD_CHART.brand} stopOpacity={0.28} />
                </linearGradient>
              </defs>
              <CartesianGrid
                strokeDasharray="3 3"
                vertical={!isHorizontal}
                horizontal
                className="stroke-border"
              />
              {isHorizontal ? (
                <>
                  <XAxis
                    type="number"
                    allowDecimals={false}
                    tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
                    axisLine={false}
                    tickLine={false}
                  />
                  <YAxis
                    type="category"
                    dataKey="label"
                    width={100}
                    tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
                    axisLine={false}
                    tickLine={false}
                  />
                </>
              ) : (
                <>
                  <XAxis
                    dataKey="label"
                    tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
                    axisLine={false}
                    tickLine={false}
                    interval={0}
                    angle={chartData.length > 5 ? -25 : 0}
                    textAnchor={chartData.length > 5 ? "end" : "middle"}
                    height={chartData.length > 5 ? 48 : 28}
                  />
                  <YAxis
                    allowDecimals={false}
                    tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
                    axisLine={false}
                    tickLine={false}
                    width={28}
                  />
                </>
              )}
              <Tooltip
                contentStyle={{
                  backgroundColor: "var(--popover)",
                  border: "1px solid var(--border)",
                  borderRadius: "8px",
                  fontSize: "12px",
                }}
                formatter={(value) => [`${value}`, valueLabel]}
              />
              <Bar
                dataKey="value"
                fill={usesPerBarFill ? undefined : `url(#${gradientId})`}
                radius={isHorizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]}
                maxBarSize={40}
              >
                {usesPerBarFill
                  ? chartData.map((row) => <Cell key={row.key} fill={row.fill} />)
                  : null}
              </Bar>
            </BarChart>
          </DashboardResponsiveChart>
        )}
      </CardContent>
    </Card>
  );
}
