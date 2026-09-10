"use client";

import { useMemo } from "react";
import { Cell, Pie, PieChart, Tooltip } from "recharts";

import {
  chartColorAt,
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
  height?: number;
  className?: string;
};

export function DashboardDonutChartImpl({
  title,
  description,
  data,
  emptyMessage = "No data to chart yet.",
  valueLabel = "Count",
  height = 220,
  className,
}: DashboardDonutChartProps) {
  const chartData = useMemo(
    () =>
      data
        .filter((row) => row.value > 0)
        .map((row, index) => ({
          ...row,
          fill:
            row.fill ??
            chartColorAt(index),
        })),
    [data],
  );
  const total = useMemo(() => chartData.reduce((sum, row) => sum + row.value, 0), [chartData]);

  return (
    <Card className={cn("flex h-full flex-col overflow-hidden rounded-xl border-border shadow-sm", className)}>
      <CardHeader className="space-y-0.5 border-b border-border/80 bg-muted/20 px-4 py-3">
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
          <div className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-center">
            <DashboardResponsiveChart height={height}>
              <PieChart>
                <Pie
                  data={chartData}
                  dataKey="value"
                  nameKey="label"
                  innerRadius="58%"
                  outerRadius="82%"
                  paddingAngle={2}
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
            <ul className="space-y-1.5 text-xs">
              {chartData.map((row) => {
                const pct = total > 0 ? Math.round((row.value / total) * 100) : 0;
                return (
                  <li key={row.key} className="flex items-center gap-2">
                    <span
                      className="h-2.5 w-2.5 shrink-0 rounded-sm"
                      style={{ backgroundColor: row.fill }}
                      aria-hidden
                    />
                    <span className="min-w-0 flex-1 truncate text-muted-foreground">{row.label}</span>
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
