"use client";

import { useId, useMemo, type ReactElement, type ReactNode } from "react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  Cell,
  Legend,
  Line,
  LineChart,
  PolarAngleAxis,
  PolarGrid,
  PolarRadiusAxis,
  Radar,
  RadarChart,
  RadialBar,
  RadialBarChart,
  Scatter,
  ScatterChart,
  Tooltip,
  XAxis,
  YAxis,
  ZAxis,
} from "recharts";

import {
  DASHBOARD_CHART,
  chartColorAt,
  type DashboardChartDatum,
  type DashboardMultiSeries,
  type DashboardScatterPoint,
} from "@/components/dashboard/dashboard-chart-utils";
import { DashboardResponsiveChart } from "@/components/dashboard/dashboard-responsive-chart";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";

type ShellProps = {
  title: string;
  description?: string;
  emptyMessage?: string;
  height?: number;
  className?: string;
  children: ReactElement;
  empty?: boolean;
  headerRight?: ReactNode;
};

function ChartShell({
  title,
  description,
  emptyMessage = "No data to chart yet.",
  height = 220,
  className,
  children,
  empty,
  headerRight,
}: ShellProps) {
  return (
    <Card className={cn("flex h-full flex-col overflow-hidden rounded-xl border-border shadow-sm", className)}>
      <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0 border-b border-border/80 bg-muted/20 px-4 py-3">
        <div className="min-w-0 space-y-0.5">
          <CardTitle className="text-sm font-medium text-foreground">{title}</CardTitle>
          {description ? (
            <p className="text-[11px] font-normal leading-snug text-muted-foreground">{description}</p>
          ) : null}
        </div>
        {headerRight}
      </CardHeader>
      <CardContent className="flex flex-1 flex-col p-4 pt-4">
        {empty ? (
          <p className="flex flex-1 items-center justify-center py-8 text-center text-xs text-muted-foreground">
            {emptyMessage}
          </p>
        ) : (
          <DashboardResponsiveChart height={height}>{children}</DashboardResponsiveChart>
        )}
      </CardContent>
    </Card>
  );
}

const tooltipStyle = {
  backgroundColor: "var(--popover)",
  border: "1px solid var(--border)",
  borderRadius: "8px",
  fontSize: "12px",
} as const;

export type DashboardMultiLineChartProps = {
  title: string;
  description?: string;
  data: DashboardMultiSeries;
  emptyMessage?: string;
  height?: number;
  className?: string;
  summary?: string;
};

export function DashboardMultiLineChartImpl({
  title,
  description,
  data,
  emptyMessage,
  height = 240,
  className,
  summary,
}: DashboardMultiLineChartProps) {
  const rows = useMemo(
    () =>
      data.categories.map((label, index) => {
        const row: Record<string, string | number> = { label };
        for (const series of data.series) {
          row[series.key] = series.values[index] ?? 0;
        }
        return row;
      }),
    [data],
  );
  const empty = rows.length === 0 || data.series.length === 0;

  return (
    <ChartShell
      title={title}
      description={description}
      emptyMessage={emptyMessage}
      height={height}
      className={className}
      empty={empty}
      headerRight={
        summary ? (
          <span className="shrink-0 rounded-md bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
            {summary}
          </span>
        ) : null
      }
    >
      <LineChart data={rows} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
        <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
        <XAxis
          dataKey="label"
          tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
          axisLine={false}
          tickLine={false}
        />
        <YAxis
          allowDecimals={false}
          tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
          axisLine={false}
          tickLine={false}
          width={28}
        />
        <Tooltip contentStyle={tooltipStyle} />
        <Legend wrapperStyle={{ fontSize: 11 }} />
        {data.series.map((series) => (
          <Line
            key={series.key}
            type="monotone"
            dataKey={series.key}
            name={series.label}
            stroke={series.color}
            strokeWidth={2}
            dot={{ r: 3 }}
            activeDot={{ r: 5 }}
          />
        ))}
      </LineChart>
    </ChartShell>
  );
}

export type DashboardStackedAreaChartProps = DashboardMultiLineChartProps;

export function DashboardStackedAreaChartImpl({
  title,
  description,
  data,
  emptyMessage,
  height = 240,
  className,
}: DashboardStackedAreaChartProps) {
  const gradientBase = useId().replace(/:/g, "");
  const rows = useMemo(
    () =>
      data.categories.map((label, index) => {
        const row: Record<string, string | number> = { label };
        for (const series of data.series) {
          row[series.key] = series.values[index] ?? 0;
        }
        return row;
      }),
    [data],
  );
  const empty = rows.length === 0 || data.series.length === 0;

  return (
    <ChartShell
      title={title}
      description={description}
      emptyMessage={emptyMessage}
      height={height}
      className={className}
      empty={empty}
    >
      <AreaChart data={rows} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
        <defs>
          {data.series.map((series) => (
            <linearGradient key={series.key} id={`${gradientBase}-${series.key}`} x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor={series.color} stopOpacity={0.45} />
              <stop offset="95%" stopColor={series.color} stopOpacity={0.05} />
            </linearGradient>
          ))}
        </defs>
        <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
        <XAxis
          dataKey="label"
          tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
          axisLine={false}
          tickLine={false}
        />
        <YAxis
          allowDecimals={false}
          tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
          axisLine={false}
          tickLine={false}
          width={28}
        />
        <Tooltip contentStyle={tooltipStyle} />
        <Legend wrapperStyle={{ fontSize: 11 }} />
        {data.series.map((series) => (
          <Area
            key={series.key}
            type="monotone"
            dataKey={series.key}
            name={series.label}
            stackId="1"
            stroke={series.color}
            fill={`url(#${gradientBase}-${series.key})`}
            strokeWidth={1.5}
          />
        ))}
      </AreaChart>
    </ChartShell>
  );
}

export type DashboardRadarChartProps = {
  title: string;
  description?: string;
  data: DashboardMultiSeries;
  emptyMessage?: string;
  height?: number;
  className?: string;
};

export function DashboardRadarChartImpl({
  title,
  description,
  data,
  emptyMessage,
  height = 260,
  className,
}: DashboardRadarChartProps) {
  const rows = useMemo(
    () =>
      data.categories.map((label, index) => {
        const row: Record<string, string | number> = { label };
        for (const series of data.series) {
          row[series.key] = series.values[index] ?? 0;
        }
        return row;
      }),
    [data],
  );
  const empty = rows.length < 3 || data.series.length === 0;

  return (
    <ChartShell
      title={title}
      description={description}
      emptyMessage={emptyMessage ?? "Need at least 3 categories for a radar chart."}
      height={height}
      className={className}
      empty={empty}
    >
      <RadarChart data={rows} cx="50%" cy="50%" outerRadius="70%">
        <PolarGrid className="stroke-border" />
        <PolarAngleAxis dataKey="label" tick={{ fontSize: 10, fill: "var(--muted-foreground)" }} />
        <PolarRadiusAxis tick={{ fontSize: 9, fill: "var(--muted-foreground)" }} axisLine={false} />
        <Tooltip contentStyle={tooltipStyle} />
        <Legend wrapperStyle={{ fontSize: 11 }} />
        {data.series.slice(0, 2).map((series) => (
          <Radar
            key={series.key}
            name={series.label}
            dataKey={series.key}
            stroke={series.color}
            fill={series.color}
            fillOpacity={0.25}
          />
        ))}
      </RadarChart>
    </ChartShell>
  );
}

export type DashboardPolarChartProps = {
  title: string;
  description?: string;
  data: DashboardChartDatum[];
  emptyMessage?: string;
  height?: number;
  className?: string;
};

export function DashboardPolarChartImpl({
  title,
  description,
  data,
  emptyMessage,
  height = 260,
  className,
}: DashboardPolarChartProps) {
  const chartData = useMemo(
    () =>
      data
        .filter((row) => row.value > 0)
        .map((row, index) => ({
          ...row,
          fill: row.fill ?? chartColorAt(index),
        })),
    [data],
  );

  return (
    <ChartShell
      title={title}
      description={description}
      emptyMessage={emptyMessage}
      height={height}
      className={className}
      empty={chartData.length === 0}
    >
      <RadialBarChart
        data={chartData}
        innerRadius="18%"
        outerRadius="90%"
        startAngle={90}
        endAngle={-270}
      >
        <RadialBar dataKey="value" background={{ fill: "var(--muted)" }} cornerRadius={4}>
          {chartData.map((row) => (
            <Cell key={row.key} fill={row.fill} />
          ))}
        </RadialBar>
        <Legend
          wrapperStyle={{ fontSize: 11 }}
          formatter={(_value, entry) => (entry?.payload as DashboardChartDatum | undefined)?.label ?? ""}
        />
        <Tooltip
          contentStyle={tooltipStyle}
          formatter={(value, _name, item) => [
            `${value}`,
            (item?.payload as DashboardChartDatum | undefined)?.label ?? "Value",
          ]}
        />
      </RadialBarChart>
    </ChartShell>
  );
}

export type DashboardBubbleChartProps = {
  title: string;
  description?: string;
  data: DashboardScatterPoint[];
  emptyMessage?: string;
  height?: number;
  className?: string;
  summary?: string;
  xLabel?: string;
  yLabel?: string;
};

export function DashboardBubbleChartImpl({
  title,
  description,
  data,
  emptyMessage,
  height = 240,
  className,
  summary,
  xLabel = "Series A",
  yLabel = "Series B",
}: DashboardBubbleChartProps) {
  return (
    <ChartShell
      title={title}
      description={description}
      emptyMessage={emptyMessage}
      height={height}
      className={className}
      empty={data.length === 0}
      headerRight={
        summary ? (
          <span className="shrink-0 rounded-md bg-sky-500/10 px-2 py-0.5 text-[11px] font-medium text-sky-700 dark:text-sky-400">
            {summary}
          </span>
        ) : null
      }
    >
      <ScatterChart margin={{ top: 8, right: 12, left: 0, bottom: 8 }}>
        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
        <XAxis
          type="number"
          dataKey="x"
          name={xLabel}
          tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
          axisLine={false}
          tickLine={false}
        />
        <YAxis
          type="number"
          dataKey="y"
          name={yLabel}
          tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
          axisLine={false}
          tickLine={false}
          width={32}
        />
        <ZAxis type="number" dataKey="z" range={[60, 400]} />
        <Tooltip
          contentStyle={tooltipStyle}
          cursor={{ strokeDasharray: "3 3" }}
          formatter={(value, name) => [`${value}`, String(name)]}
          labelFormatter={(_label, payload) => {
            const point = payload?.[0]?.payload as DashboardScatterPoint | undefined;
            return point?.label ?? "";
          }}
        />
        <Scatter data={data} fill={DASHBOARD_CHART.brand}>
          {data.map((point) => (
            <Cell key={point.key} fill={point.fill ?? DASHBOARD_CHART.brand} fillOpacity={0.75} />
          ))}
        </Scatter>
      </ScatterChart>
    </ChartShell>
  );
}

export type DashboardScatterChartProps = DashboardBubbleChartProps & {
  /** Optional second series plotted as another scatter set */
  secondary?: DashboardScatterPoint[];
};

export function DashboardScatterChartImpl({
  title,
  description,
  data,
  secondary = [],
  emptyMessage,
  height = 240,
  className,
  xLabel = "Rank",
  yLabel = "Value",
}: DashboardScatterChartProps) {
  const packs = useMemo(
    () =>
      [
        { key: "primary", label: "Primary", points: data, color: chartColorAt(0) },
        { key: "secondary", label: "Compare", points: secondary, color: chartColorAt(1) },
      ].filter((pack) => pack.points.length > 0),
    [data, secondary],
  );

  return (
    <ChartShell
      title={title}
      description={description}
      emptyMessage={emptyMessage}
      height={height}
      className={className}
      empty={packs.length === 0}
    >
      <ScatterChart margin={{ top: 8, right: 12, left: 0, bottom: 8 }}>
        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
        <XAxis
          type="number"
          dataKey="x"
          name={xLabel}
          tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
          axisLine={false}
          tickLine={false}
        />
        <YAxis
          type="number"
          dataKey="y"
          name={yLabel}
          tick={{ fontSize: 10, fill: "var(--muted-foreground)" }}
          axisLine={false}
          tickLine={false}
          width={32}
        />
        <Tooltip
          contentStyle={tooltipStyle}
          cursor={{ strokeDasharray: "3 3" }}
          labelFormatter={(_label, payload) => {
            const point = payload?.[0]?.payload as DashboardScatterPoint | undefined;
            return point?.label ?? "";
          }}
        />
        <Legend wrapperStyle={{ fontSize: 11 }} />
        {packs.map((pack) => (
          <Scatter key={pack.key} name={pack.label} data={pack.points} fill={pack.color}>
            {pack.points.map((point) => (
              <Cell key={point.key} fill={point.fill ?? pack.color} />
            ))}
          </Scatter>
        ))}
      </ScatterChart>
    </ChartShell>
  );
}
