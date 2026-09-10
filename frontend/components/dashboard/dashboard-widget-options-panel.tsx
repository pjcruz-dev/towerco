"use client";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import type {
  DashboardCatalogEntry,
  DashboardWidgetOptions,
  DashboardWidgetSpan,
} from "@/lib/ui/dashboard-widget-catalog";
import {
  DASHBOARD_DATA_SOURCE_LABELS,
  type DashboardDataSourceId,
} from "@/lib/ui/dashboard-widget-data";
import type { ProjectOneKpi } from "@/modules/project-one/types";

const SPAN_OPTIONS: Array<{ id: DashboardWidgetSpan; label: string }> = [
  { id: "full", label: "Full" },
  { id: "half", label: "½" },
  { id: "third", label: "⅓" },
  { id: "quarter", label: "¼" },
];

const CHART_KINDS = new Set([
  "chart_bar",
  "chart_bar_horizontal",
  "chart_donut",
  "chart_line",
  "chart_area",
  "chart_multi_line",
  "chart_stacked_area",
  "chart_radar",
  "chart_polar",
  "chart_bubble",
  "chart_scatter",
  "bar_list",
  "list_progress",
  "stacked_comparison",
  "funnel_stages",
  "pipeline_chevrons",
  "kpi_sparkline",
]);

const LIST_KINDS = new Set(["list_activity", "list_users", "list_leaderboard"]);
const KPI_PICK_KINDS = new Set(["kpi_single", "kpi_gauge", "kpi_sparkline"]);
const KPI_ROW_KINDS = new Set(["kpi_metric_row", "kpi_hero_chart"]);
const SORTABLE_KINDS = new Set([
  ...CHART_KINDS,
  ...LIST_KINDS,
  "bar_list",
  "list_progress",
  "stacked_comparison",
]);
const THRESHOLD_KINDS = new Set(["kpi_gauge", "list_progress", "bar_list"]);

type Props = {
  widgetId: string;
  label: string;
  entry?: DashboardCatalogEntry;
  options?: DashboardWidgetOptions;
  span: DashboardWidgetSpan;
  dataSources: DashboardDataSourceId[];
  kpis?: ProjectOneKpi[];
  removable?: boolean;
  onTitleChange: (title: string) => void;
  onSpanChange: (span: DashboardWidgetSpan) => void;
  onSettingsChange: (patch: Record<string, string | number | boolean | undefined>) => void;
  onDuplicate?: () => void;
  onRemove?: () => void;
};

export function DashboardWidgetOptionsPanel({
  label,
  entry,
  options,
  span,
  dataSources,
  kpis = [],
  removable = true,
  onTitleChange,
  onSpanChange,
  onSettingsChange,
  onDuplicate,
  onRemove,
}: Props) {
  const widthOptions =
    entry?.allowedSpans && entry.allowedSpans.length > 0
      ? entry.allowedSpans
      : SPAN_OPTIONS.map((item) => item.id);
  const settings = { ...entry?.defaultSettings, ...options?.settings };
  const kind = entry?.kind;
  const isCustom = kind === "custom" || !kind;
  const showDataSource = Boolean(kind && (CHART_KINDS.has(kind) || LIST_KINDS.has(kind)));
  const showKpiPick = Boolean(kind && KPI_PICK_KINDS.has(kind) && kpis.length > 0);
  const showKpiRowSource = Boolean(kind && KPI_ROW_KINDS.has(kind));
  const showLimit = Boolean(kind && (LIST_KINDS.has(kind) || CHART_KINDS.has(kind)));
  const showSort = Boolean(kind && SORTABLE_KINDS.has(kind));
  const showThreshold = Boolean(kind && THRESHOLD_KINDS.has(kind));
  const listSources = dataSources.filter(
    (id) => id === "auto" || id === "activity" || id === "audit" || id === "people",
  );
  const chartSources = dataSources.filter(
    (id) => id !== "activity" && id !== "audit" && id !== "people",
  );
  const sourceChoices = kind && LIST_KINDS.has(kind) ? listSources : chartSources;
  const selectedSource = String(settings.dataSource ?? "auto");
  const effectiveSource = sourceChoices.includes(selectedSource as DashboardDataSourceId)
    ? selectedSource
    : "auto";

  return (
    <div className="space-y-3">
      {isCustom ? (
        <p className="rounded-md border border-border bg-muted/30 px-2 py-1.5 text-[11px] leading-snug text-muted-foreground">
          Title, width, density, and collapse apply to this section. Duplicate keeps the same data with a
          separate layout slot.
        </p>
      ) : null}

      <div className="space-y-1">
        <p className="text-[11px] font-medium text-muted-foreground">Title</p>
        <Input
          value={options?.title ?? ""}
          placeholder={label}
          className="h-8"
          onChange={(e) => onTitleChange(e.target.value)}
        />
      </div>

      <div className="space-y-1">
        <p className="text-[11px] font-medium text-muted-foreground">Width</p>
        <div className="flex flex-wrap gap-1">
          {widthOptions.map((id) => (
            <Button
              key={id}
              type="button"
              size="sm"
              variant={span === id ? "secondary" : "outline"}
              className="h-7 px-2 text-xs"
              onClick={() => onSpanChange(id)}
            >
              {SPAN_OPTIONS.find((o) => o.id === id)?.label ?? id}
            </Button>
          ))}
        </div>
      </div>

      {showDataSource && sourceChoices.length > 1 ? (
        <div className="space-y-1">
          <p className="text-[11px] font-medium text-muted-foreground">Data source</p>
          <select
            className="h-8 w-full rounded-md border border-border bg-background px-2 text-xs"
            value={effectiveSource}
            onChange={(e) => onSettingsChange({ dataSource: e.target.value })}
          >
            {sourceChoices.map((id) => (
              <option key={id} value={id}>
                {DASHBOARD_DATA_SOURCE_LABELS[id]}
              </option>
            ))}
          </select>
          <p className="text-[10px] text-muted-foreground">Uses live module metrics available on this page.</p>
        </div>
      ) : null}

      {showKpiRowSource && dataSources.includes("secondaryKpis") ? (
        <div className="space-y-1">
          <p className="text-[11px] font-medium text-muted-foreground">KPI set</p>
          <select
            className="h-8 w-full rounded-md border border-border bg-background px-2 text-xs"
            value={String(settings.dataSource ?? "auto")}
            onChange={(e) => onSettingsChange({ dataSource: e.target.value })}
          >
            <option value="auto">Primary KPIs</option>
            <option value="secondaryKpis">Secondary KPIs</option>
          </select>
        </div>
      ) : null}

      {showKpiPick ? (
        <div className="space-y-1">
          <p className="text-[11px] font-medium text-muted-foreground">KPI metric</p>
          <select
            className="h-8 w-full rounded-md border border-border bg-background px-2 text-xs"
            value={String(settings.kpiKey ?? kpis[0]?.key ?? "")}
            onChange={(e) => onSettingsChange({ kpiKey: e.target.value })}
          >
            {kpis.map((kpi) => (
              <option key={kpi.key} value={kpi.key}>
                {kpi.label}
              </option>
            ))}
          </select>
        </div>
      ) : null}

      {showLimit ? (
        <div className="space-y-1">
          <p className="text-[11px] font-medium text-muted-foreground">Max items</p>
          <div className="flex flex-wrap gap-1">
            {[5, 8, 12, 20].map((n) => (
              <Button
                key={n}
                type="button"
                size="sm"
                variant={Number(settings.limit ?? 8) === n ? "secondary" : "outline"}
                className="h-7 px-2 text-xs"
                onClick={() => onSettingsChange({ limit: n })}
              >
                {n}
              </Button>
            ))}
          </div>
        </div>
      ) : null}

      {showSort ? (
        <div className="space-y-1">
          <p className="text-[11px] font-medium text-muted-foreground">Sort</p>
          <div className="flex flex-wrap gap-1">
            {(
              [
                { id: "desc", label: "High → low" },
                { id: "asc", label: "Low → high" },
                { id: "none", label: "As provided" },
              ] as const
            ).map((item) => {
              const active =
                settings.sort === item.id ||
                (item.id === "desc" && (settings.sort == null || settings.sort === ""));
              return (
                <Button
                  key={item.id}
                  type="button"
                  size="sm"
                  variant={active ? "secondary" : "outline"}
                  className="h-7 px-2 text-xs"
                  onClick={() => onSettingsChange({ sort: item.id })}
                >
                  {item.label}
                </Button>
              );
            })}
          </div>
        </div>
      ) : null}

      {showThreshold ? (
        <div className="space-y-1">
          <p className="text-[11px] font-medium text-muted-foreground">Highlight threshold</p>
          <Input
            type="number"
            className="h-8"
            placeholder="e.g. 80"
            value={settings.threshold != null ? String(settings.threshold) : ""}
            onChange={(e) => {
              const raw = e.target.value.trim();
              if (!raw) {
                onSettingsChange({ threshold: undefined });
                return;
              }
              const n = Number(raw);
              onSettingsChange({ threshold: Number.isFinite(n) ? n : undefined });
            }}
          />
          <p className="text-[10px] text-muted-foreground">
            Values at or above this mark get emphasis (gauge / progress).
          </p>
        </div>
      ) : null}

      {kind === "page_tip" ? (
        <div className="space-y-2">
          <div className="space-y-1">
            <p className="text-[11px] font-medium text-muted-foreground">Tip title</p>
            <Input
              className="h-8"
              value={settings.tipTitle != null ? String(settings.tipTitle) : ""}
              placeholder="Tip"
              onChange={(e) => onSettingsChange({ tipTitle: e.target.value || undefined })}
            />
          </div>
          <div className="space-y-1">
            <p className="text-[11px] font-medium text-muted-foreground">Tip body</p>
            <textarea
              className="min-h-[4.5rem] w-full rounded-md border border-border bg-background px-2 py-1.5 text-xs text-foreground"
              value={settings.tipBody != null ? String(settings.tipBody) : ""}
              placeholder="Guidance for operators on this page…"
              onChange={(e) => onSettingsChange({ tipBody: e.target.value || undefined })}
            />
          </div>
        </div>
      ) : null}

      <label className="flex items-center gap-2 text-xs text-foreground">
        <Checkbox
          className="size-4"
          checked={settings.showDescription === true}
          onCheckedChange={(value) => onSettingsChange({ showDescription: value === true })}
        />
        {isCustom ? "Show catalog description" : "Show description under title"}
      </label>

      <label className="flex items-center gap-2 text-xs text-foreground">
        <Checkbox
          className="size-4"
          checked={settings.compact === true}
          onCheckedChange={(value) => onSettingsChange({ compact: value === true })}
        />
        Compact density
      </label>

      <label className="flex items-center gap-2 text-xs text-foreground">
        <Checkbox
          className="size-4"
          checked={settings.collapsed === true}
          onCheckedChange={(value) => onSettingsChange({ collapsed: value === true })}
        />
        Collapse body (title bar only)
      </label>

      <div className="flex flex-wrap gap-2 pt-1">
        {onDuplicate ? (
          <Button type="button" size="sm" variant="outline" className="h-7 text-xs" onClick={onDuplicate}>
            Duplicate
          </Button>
        ) : null}
        {removable && onRemove ? (
          <Button
            type="button"
            size="sm"
            variant="ghost"
            className="h-7 text-xs text-destructive"
            onClick={onRemove}
          >
            Remove from dashboard
          </Button>
        ) : null}
      </div>
    </div>
  );
}
