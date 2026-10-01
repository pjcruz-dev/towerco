"use client";

import { useMemo } from "react";
import { Cell, Pie, PieChart, Tooltip } from "recharts";

import {
  resolveChartFill,
  type DashboardChartDatum,
} from "@/components/dashboard/dashboard-chart-utils";
import { DashboardResponsiveChart } from "@/components/dashboard/dashboard-responsive-chart";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";

export type DashboardDonutChartProps = {
  title: string;
  description?: string;
  data: DashboardChartDatum[];
  emptyMessage?: string;
  valueLabel?: string;
  /** donut = ring with center total, pie = full disc, semi = half ring. */
  shape?: "donut" | "pie" | "semi";
  height?: number;
  className?: string;
};

export function DashboardDonutChartImpl({
  title,
  description,
  data,
  emptyMessage = "No data to chart yet.",
  valueLabel = "Count",
  shape = "donut",
  height = 220,
  className,
}: DashboardDonutChartProps) {
  const chartData = useMemo(
    () =>
      data
        .filter((row) => row.value > 0)
        .map((row, index) => ({
          ...row,
          fill: resolveChartFill(row, index),
        })),
    [data],
  );
  const total = useMemo(() => chartData.reduce((sum, row) => sum + row.value, 0), [chartData]);

  return (
    <Card className={cn("flex h-full flex-col overflow-hidden rounded-xl border-border shadow-sm", className)}>
      <CardHeader className="space-y-0.5 px-5 pb-0 pt-5">
        <CardTitle className="text-sm font-medium text-foreground">{title}</CardTitle>
        {description ? (
          <p className="text-[11px] font-normal leading-snug text-muted-foreground">{description}</p>
        ) : null}
      </CardHeader>
      <CardContent className="flex flex-1 flex-col p-4 pt-4">
        {chartData.length === 0 || total === 0 ? (
          <p className="flex flex-1 items-center justify-center py-8 text-center text-xs text-muted-foreground">
            {emptyMessage}
          </p>
        ) : (
          <div className="flex flex-col items-center">
            <div className="relative w-full min-w-0">
            <DashboardResponsiveChart height={shape === "semi" ? Math.max(160, height - 24) : height}>
              <PieChart>
                <Pie
                  data={chartData}
                  dataKey="value"
                  nameKey="label"
                  innerRadius={shape === "pie" ? 0 : shape === "semi" ? "62%" : "58%"}
                  outerRadius={shape === "semi" ? "100%" : "82%"}
                  startAngle={shape === "semi" ? 180 : 0}
                  endAngle={shape === "semi" ? 0 : 360}
                  cy={shape === "semi" ? "78%" : "50%"}
                  paddingAngle={shape === "pie" ? 1 : 2}
                  strokeWidth={0}
                >
                  {chartData.map((row) => (
                    <Cell key={row.key} fill={row.fill} />
                  ))}
                </Pie>
                <Tooltip
                  contentStyle={{
                    backgroundColor: "var(--popover)",
                    border: "1px solid var(--border)",
                    borderRadius: "8px",
                    fontSize: "12px",
                  }}
                  formatter={(value, _name, item) => {
                    const pct = total > 0 ? Math.round(((Number(value) || 0) / total) * 100) : 0;
                    return [`${value} (${pct}%)`, item?.payload?.label ?? valueLabel];
                  }}
                />
              </PieChart>
            </DashboardResponsiveChart>
            {shape === "donut" ? (
              <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <p className="text-xl font-semibold tabular-nums tracking-tight text-foreground">
                  {total >= 1000 ? `${(total / 1000).toFixed(total >= 10000 ? 0 : 1)}K` : total.toLocaleString()}
                </p>
                <p className="text-[11px] text-muted-foreground">{valueLabel}</p>
              </div>
            ) : null}
            </div>
            <ul className="mt-1 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs">
              {chartData.map((row) => {
                const pct = total > 0 ? Math.round((row.value / total) * 100) : 0;
                return (
                  <li key={row.key} className="flex items-center gap-1.5">
                    <span
                      className="h-2 w-2 shrink-0 rounded-sm"
                      style={{ backgroundColor: row.fill }}
                      aria-hidden
                    />
                    <span className="text-muted-foreground">{row.label}</span>
                    <span className="tabular-nums font-medium text-foreground">
                      {row.value}
                      <span className="ml-1 font-normal text-muted-foreground">({pct}%)</span>
                    </span>
                  </li>
                );
              })}
            </ul>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
