"use client";

import { useMemo } from "react";
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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

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
  const chartData = useMemo(
    () =>
      data.map((row) => ({
        ...row,
        fill: row.fill ?? DASHBOARD_CHART.brand,
      })),
    [data],
  );
  const hasData = chartData.some((row) => row.value > 0);

  return (
    <Card className={className ?? "shadow-sm"}>
      <CardHeader className="border-b pb-3">
        <CardTitle className="text-base font-medium">{title}</CardTitle>
        {description ? (
          <p className="text-xs font-normal text-muted-foreground">{description}</p>
        ) : null}
      </CardHeader>
      <CardContent className="pt-4">
        {!hasData ? (
          <p className="py-8 text-center text-sm text-muted-foreground">{emptyMessage}</p>
        ) : (
          <DashboardResponsiveChart height={height}>
            <AreaChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
              <defs>
                <linearGradient id="analyticsArea" x1="0" y1="0" x2="0" y2="1">
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
                fill="url(#analyticsArea)"
                strokeWidth={2}
              />
            </AreaChart>
          </DashboardResponsiveChart>
        )}
      </CardContent>
    </Card>
  );
}
