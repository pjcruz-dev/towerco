"use client";

import { DashboardBarChart } from "@/components/dashboard/dashboard-bar-chart";
import { DashboardCatalogWidgetShell } from "@/components/dashboard/dashboard-catalog-widget-shell";
import { DashboardDonutChart } from "@/components/dashboard/dashboard-donut-chart";
import {
  DashboardBubbleChart,
  DashboardMultiLineChart,
  DashboardPolarChart,
  DashboardRadarChart,
  DashboardScatterChart,
  DashboardStackedAreaChart,
} from "@/components/dashboard/dashboard-extended-charts";
import { DashboardLineChart } from "@/components/dashboard/dashboard-line-chart";
import { DashboardWidget } from "@/components/dashboard/dashboard-widget";
import {
  WidgetActivityList,
  WidgetAttentionBanner,
  WidgetExportsTeaser,
  WidgetGauge,
  WidgetHeroBanner,
  WidgetPageTip,
  WidgetPeopleList,
  WidgetPipelineStrip,
  WidgetProgressRows,
  WidgetShortcutGrid,
  WidgetSingleMetric,
  WidgetSparkBars,
} from "@/components/dashboard/widgets/widget-primitives";
import { KpiStrip } from "@/components/project-one/kpi-strip";
import type {
  DashboardCatalogEntry,
  DashboardWidgetOptions,
} from "@/lib/ui/dashboard-widget-catalog";
import { parseKpiCardOverrides } from "@/lib/ui/dashboard-kpi-card-options";
import {
  activityFromDataSource,
  bubbleFromData,
  multiSeriesFromData,
  scatterFromDataSource,
  seriesFromDataSource,
  type DashboardNormalizedData,
} from "@/lib/ui/dashboard-widget-data";

type KindRenderProps = {
  entry: DashboardCatalogEntry;
  data: DashboardNormalizedData;
  title?: string;
  options?: DashboardWidgetOptions;
};

type SeriesRow = { key: string; label: string; value: number };
type SeriesSort = "asc" | "desc" | "none";

function titleOf(entry: DashboardCatalogEntry, title?: string) {
  return title?.trim() || entry.label;
}

function limitOf(options?: DashboardWidgetOptions, fallback = 8) {
  const limit = options?.settings?.limit;
  return typeof limit === "number" && limit > 0 ? limit : fallback;
}

function resolveSeriesSort(value: unknown): SeriesSort {
  if (value === "asc" || value === "desc" || value === "none") return value;
  return "desc";
}

function sortSeries(rows: SeriesRow[], sort: SeriesSort): SeriesRow[] {
  if (sort === "asc") return [...rows].sort((a, b) => a.value - b.value);
  if (sort === "none") return rows;
  return [...rows].sort((a, b) => b.value - a.value);
}

/** Prefer the selected source; if empty, fall back so Auto/empty defaults still chart. */
function seriesForChart(
  data: DashboardNormalizedData,
  source: string,
  fallback: "primary" | "donut" | "bar" = "primary",
): SeriesRow[] {
  const selected = seriesFromDataSource(data, source, fallback);
  if (selected.length > 0 || source === "auto" || !source) return selected;
  return seriesFromDataSource(data, "auto", fallback);
}

function thresholdOf(options?: DashboardWidgetOptions): number | undefined {
  const value = options?.settings?.threshold;
  return typeof value === "number" && Number.isFinite(value) ? value : undefined;
}

/**
 * Render a catalog kind from normalized module data — respects per-widget options.
 */
export function DashboardKindWidget({ entry, data, title, options }: KindRenderProps) {
  const heading = titleOf(entry, title);
  const settings = { ...entry.defaultSettings, ...options?.settings };
  const description =
    settings.showDescription === true ? entry.description : undefined;
  const dense = settings.compact === true;
  const source = settings.dataSource ? String(settings.dataSource) : "auto";
  const limit = typeof settings.limit === "number" && settings.limit > 0 ? settings.limit : 8;
  const sort = resolveSeriesSort(settings.sort);
  const threshold =
    typeof settings.threshold === "number" && Number.isFinite(settings.threshold)
      ? settings.threshold
      : undefined;
  const multi = multiSeriesFromData(data, limit);

  switch (entry.kind) {
    case "hero_banner": {
      const hero = data.hero;
      if (!hero) break;
      const showDescription = settings.showDescription !== false;
      const showCta = settings.showCta === true;
      return (
        <WidgetHeroBanner
          title={heading !== entry.label ? heading : hero.title}
          description={showDescription ? hero.description : undefined}
          ctaHref={hero.ctaHref}
          ctaLabel={hero.ctaLabel}
          showCta={showCta}
          compact={dense}
        />
      );
    }
    case "kpi_metric_row":
    case "kpi_hero_chart": {
      const useSecondary = source === "secondaryKpis" && (data.secondaryKpis?.length ?? 0) > 0;
      const cardOptions = parseKpiCardOverrides(options?.settings?.kpiCards);
      return (
        <KpiStrip
          items={useSecondary ? (data.secondaryKpis ?? []) : data.kpis}
          cardOptions={cardOptions}
        />
      );
    }
    case "kpi_single": {
      const kpiKey = options?.settings?.kpiKey ? String(options.settings.kpiKey) : undefined;
      const kpi = (kpiKey ? data.kpis.find((item) => item.key === kpiKey) : null) ?? data.kpis[0];
      if (!kpi) break;
      return (
        <DashboardWidget title={heading} description={description} dense={dense}>
          <WidgetSingleMetric
            value={kpi.value}
            label={kpi.label}
            change={kpi.change}
            tone={kpi.tone ?? "neutral"}
          />
        </DashboardWidget>
      );
    }
    case "kpi_sparkline": {
      const series = sortSeries(seriesForChart(data, source, "primary"), sort).slice(0, limit);
      const kpiKey = options?.settings?.kpiKey ? String(options.settings.kpiKey) : undefined;
      const kpi = (kpiKey ? data.kpis.find((item) => item.key === kpiKey) : null) ?? data.kpis[0];
      return (
        <DashboardWidget title={heading} description={description} dense={dense}>
          <WidgetSparkBars
            series={series}
            value={kpi?.value ?? "—"}
            label={kpi?.label ?? entry.label}
          />
        </DashboardWidget>
      );
    }
    case "kpi_gauge": {
      const kpiKey = options?.settings?.kpiKey ? String(options.settings.kpiKey) : undefined;
      const kpi = (kpiKey ? data.kpis.find((item) => item.key === kpiKey) : null) ?? data.kpis[0];
      const values = data.kpis
        .map((item) => (typeof item.value === "number" ? item.value : Number(item.value)))
        .filter((n) => Number.isFinite(n));
      const value =
        typeof kpi?.value === "number" ? kpi.value : Number(kpi?.value) || values[0] || 0;
      const max = Math.max(values.reduce((a, b) => a + b, 0), value, threshold ?? 0, 1);
      const tone =
        threshold != null && value >= threshold
          ? "warning"
          : (kpi?.tone ?? "neutral");
      return (
        <DashboardWidget title={heading} description={description} dense={dense}>
          <WidgetGauge
            label={kpi?.label ?? entry.label}
            value={value}
            max={max}
            tone={tone}
          />
        </DashboardWidget>
      );
    }
    case "chart_bar":
      return (
        <DashboardBarChart
          title={heading}
          description={description}
          data={sortSeries(seriesForChart(data, source, "primary"), sort).slice(0, limit)}
          layout="vertical"
          emptyMessage="No volume to chart."
          height={dense ? 180 : 220}
        />
      );
    case "chart_bar_horizontal":
      return (
        <DashboardBarChart
          title={heading}
          description={description}
          data={sortSeries(seriesForChart(data, source, "bar"), sort).slice(0, limit)}
          layout="horizontal"
          emptyMessage="No volume to chart."
          height={dense ? 180 : 240}
        />
      );
    case "chart_donut":
      return (
        <DashboardDonutChart
          title={heading}
          description={description}
          data={sortSeries(seriesForChart(data, source, "donut"), sort).slice(0, limit)}
          emptyMessage="No breakdown to chart."
          height={dense ? 180 : 220}
        />
      );
    case "chart_line":
    case "chart_area":
      return (
        <DashboardLineChart
          title={heading}
          description={description}
          data={sortSeries(seriesForChart(data, source, "primary"), sort).slice(0, limit)}
          emptyMessage="No series to chart."
          height={dense ? 180 : 220}
        />
      );
    case "chart_multi_line":
      return (
        <DashboardMultiLineChart
          title={heading}
          description={description}
          data={multi}
          emptyMessage="Need at least one breakdown series."
          height={dense ? 200 : 240}
        />
      );
    case "chart_stacked_area":
      return (
        <DashboardStackedAreaChart
          title={heading}
          description={description}
          data={multi}
          emptyMessage="Need at least one breakdown series."
          height={dense ? 200 : 240}
        />
      );
    case "chart_radar":
      return (
        <DashboardRadarChart
          title={heading}
          description={description}
          data={multi}
          height={dense ? 220 : 260}
        />
      );
    case "chart_polar":
      return (
        <DashboardPolarChart
          title={heading}
          description={description}
          data={sortSeries(seriesForChart(data, source, "donut"), sort).slice(0, limit)}
          height={dense ? 220 : 260}
        />
      );
    case "chart_bubble":
      return (
        <DashboardBubbleChart
          title={heading}
          description={description}
          data={bubbleFromData(data, limit)}
          height={dense ? 200 : 240}
          xLabel={multi.series[0]?.label ?? "Series A"}
          yLabel={multi.series[1]?.label ?? "Series B"}
        />
      );
    case "chart_scatter": {
      const primary = scatterFromDataSource(data, source, limit);
      const secondKey = multi.series[1]?.key;
      const secondary =
        secondKey && secondKey !== "auto"
          ? scatterFromDataSource(data, secondKey, limit)
          : [];
      return (
        <DashboardScatterChart
          title={heading}
          description={description}
          data={primary}
          secondary={secondary}
          height={dense ? 200 : 240}
        />
      );
    }
    case "bar_list":
    case "list_progress":
    case "stacked_comparison":
      return (
        <DashboardWidget title={heading} description={description} dense={dense}>
          <WidgetProgressRows
            rows={sortSeries(seriesForChart(data, source, "bar"), sort)
              .slice(0, limit)
              .map((row) => ({
                key: row.key,
                label: row.label,
                value: row.value,
                tone:
                  threshold != null && row.value >= threshold ? ("warning" as const) : undefined,
              }))}
          />
        </DashboardWidget>
      );
    case "funnel_stages":
    case "pipeline_chevrons":
      return (
        <DashboardWidget title={heading} description={description} dense={dense}>
          <WidgetPipelineStrip
            data={sortSeries(seriesForChart(data, source, "primary"), sort).slice(0, limit)}
          />
        </DashboardWidget>
      );
    case "list_activity":
      return (
        <DashboardWidget
          title={heading}
          description={description}
          dense={dense}
          contentClassName="p-0 pt-0"
        >
          <div className={dense ? "p-2.5" : "p-3"}>
            <WidgetActivityList items={activityFromDataSource(data, source).slice(0, limit)} />
          </div>
        </DashboardWidget>
      );
    case "list_users":
    case "list_leaderboard":
      return (
        <DashboardWidget title={heading} description={description} dense={dense}>
          <WidgetPeopleList
            people={(data.people ?? []).slice(0, limit)}
            ranked={entry.kind === "list_leaderboard"}
          />
        </DashboardWidget>
      );
    case "shortcuts":
      return (
        <DashboardWidget title={heading} description={description} dense={dense}>
          <WidgetShortcutGrid items={data.shortcuts ?? []} />
        </DashboardWidget>
      );
    case "page_attention":
      return (
        <DashboardWidget title={heading} description={description} dense={dense}>
          <WidgetAttentionBanner items={data.attention ?? []} />
        </DashboardWidget>
      );
    case "page_tip":
      return (
        <DashboardWidget title={heading} description={description} dense={dense}>
          <WidgetPageTip
            title={settings.tipTitle ? String(settings.tipTitle) : undefined}
            body={settings.tipBody ? String(settings.tipBody) : undefined}
          />
        </DashboardWidget>
      );
    case "page_exports": {
      const teaser = data.exportsTeaser;
      return (
        <DashboardWidget title={heading} description={description} dense={dense}>
          <WidgetExportsTeaser
            href={teaser?.href}
            label={teaser?.label}
            count={teaser?.count}
            message={teaser?.message}
          />
        </DashboardWidget>
      );
    }
    default:
      break;
  }

  return <DashboardCatalogWidgetShell entry={entry} title={title} unbound />;
}
