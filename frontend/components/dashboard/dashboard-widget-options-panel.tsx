"use client";

import { ChevronDown } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import {
  DASHBOARD_KPI_ACCENTS,
  DASHBOARD_KPI_ICONS,
  DASHBOARD_KPI_LAYOUTS,
  DASHBOARD_KPI_TONES,
  DASHBOARD_KPI_VIZ_STYLES,
  parseKpiCardOverrides,
  patchKpiCardOverride,
  type DashboardKpiAccent,
  type DashboardKpiIconId,
  type DashboardKpiLayout,
  type DashboardKpiSparkStyle,
  type DashboardKpiTone,
} from "@/lib/ui/dashboard-kpi-card-options";
import type {
  DashboardCatalogEntry,
  DashboardWidgetOptions,
  DashboardWidgetSpan,
} from "@/lib/ui/dashboard-widget-catalog";
import {
  DASHBOARD_DATA_SOURCE_LABELS,
  type DashboardDataSourceId,
} from "@/lib/ui/dashboard-widget-data";
import { cn } from "@/lib/utils";
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
  const showKpiCardAppearance = Boolean(kind && KPI_ROW_KINDS.has(kind) && kpis.length > 0);
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
  const effectiveSource = (sourceChoices as string[]).includes(selectedSource)
    ? selectedSource
    : "auto";
  const kpiCardOverrides = parseKpiCardOverrides(settings.kpiCards);
  const [expandedKpiKey, setExpandedKpiKey] = useState<string | null>(
    () => kpis[0]?.key ?? null,
  );

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

      {showKpiCardAppearance ? (
        <div className="space-y-2">
          <div className="flex items-center justify-between gap-2">
            <p className="text-[11px] font-medium text-muted-foreground">Card appearance</p>
            <p className="text-[10px] text-muted-foreground">Expand one card to edit</p>
          </div>
          <div className="space-y-1.5">
            {kpis.map((kpi) => {
              const kpiKey = kpi.key;
              const override = kpiCardOverrides[kpiKey] ?? {};
              const accent: DashboardKpiAccent = override.accent ?? "auto";
              const icon: DashboardKpiIconId = override.icon ?? "auto";
              const tone: DashboardKpiTone = override.tone ?? kpi.tone ?? "neutral";
              const visible = override.hidden !== true;
              const vizStyle: DashboardKpiSparkStyle = override.sparkStyle ?? "auto";
              const cardLayout: DashboardKpiLayout = override.layout ?? "auto";
              const tinted = override.tinted === true;
              const expanded = expandedKpiKey === kpiKey;
              const vizLabel =
                DASHBOARD_KPI_VIZ_STYLES.find((item) => item.id === vizStyle)?.label ?? "Auto";

              const patchCard = (
                patch: Parameters<typeof patchKpiCardOverride>[2],
              ) => {
                onSettingsChange({
                  kpiCards: patchKpiCardOverride(settings.kpiCards, kpiKey, patch),
                });
              };

              return (
                <div
                  key={kpiKey}
                  className={cn(
                    "overflow-hidden rounded-lg border border-border bg-muted/20",
                    !visible && "opacity-60",
                  )}
                >
                  <div className="flex items-center gap-1.5 px-2 py-1.5">
                    <Checkbox
                      className="size-4 shrink-0"
                      checked={visible}
                      onCheckedChange={(value) => patchCard({ hidden: value !== true })}
                      aria-label={`Show ${kpi.label}`}
                    />
                    <button
                      type="button"
                      className="flex min-w-0 flex-1 items-center gap-2 rounded-md px-1 py-0.5 text-left hover:bg-background/60"
                      aria-expanded={expanded}
                      onClick={() =>
                        setExpandedKpiKey((current) => (current === kpiKey ? null : kpiKey))
                      }
                    >
                      <span className="min-w-0 flex-1 truncate text-xs font-medium text-foreground">
                        {override.label?.trim() || kpi.label}
                      </span>
                      <span className="hidden shrink-0 text-[10px] text-muted-foreground sm:inline">
                        {vizLabel}
                      </span>
                      <span
                        className={cn(
                          "size-2.5 shrink-0 rounded-full border border-border",
                          accent === "auto"
                            ? "bg-gradient-to-br from-sky-400 to-emerald-400"
                            : DASHBOARD_KPI_ACCENTS.find((item) => item.id === accent)?.swatch,
                        )}
                        aria-hidden
                      />
                      <ChevronDown
                        className={cn(
                          "size-3.5 shrink-0 text-muted-foreground transition-transform",
                          expanded && "rotate-180",
                        )}
                        aria-hidden
                      />
                    </button>
                  </div>

                  {expanded ? (
                    <div className="space-y-2.5 border-t border-border bg-card/60 px-2.5 py-2.5">
                      <div className="space-y-1">
                        <p className="text-[10px] font-medium text-muted-foreground">Color</p>
                        <div className="flex flex-wrap gap-1.5">
                          {DASHBOARD_KPI_ACCENTS.map((item) => {
                            const active = accent === item.id;
                            return (
                              <button
                                key={item.id}
                                type="button"
                                title={item.label}
                                aria-label={item.label}
                                aria-pressed={active}
                                className={cn(
                                  "size-6 rounded-md border border-border transition-shadow",
                                  item.swatch,
                                  active && "ring-2 ring-ring ring-offset-1 ring-offset-background",
                                )}
                                onClick={() => patchCard({ accent: item.id })}
                              />
                            );
                          })}
                        </div>
                      </div>

                      <div className="space-y-1">
                        <p className="text-[10px] font-medium text-muted-foreground">Visualization</p>
                        <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                          {DASHBOARD_KPI_VIZ_STYLES.map((item) => {
                            const active = vizStyle === item.id;
                            return (
                              <button
                                key={item.id}
                                type="button"
                                title={item.hint}
                                className={cn(
                                  "rounded-md border px-2 py-1.5 text-left transition-colors",
                                  active
                                    ? "border-foreground/20 bg-background shadow-sm"
                                    : "border-border/60 bg-background/40 hover:bg-background",
                                )}
                                onClick={() =>
                                  patchCard({
                                    sparkStyle: item.id,
                                    showSpark: item.id === "none" ? false : true,
                                  })
                                }
                              >
                                <span className="block text-[11px] font-medium text-foreground">
                                  {item.label}
                                </span>
                                <span className="mt-0.5 block line-clamp-2 text-[9px] leading-snug text-muted-foreground">
                                  {item.hint}
                                </span>
                              </button>
                            );
                          })}
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1">
                          <p className="text-[10px] font-medium text-muted-foreground">Icon</p>
                          <select
                            className="h-8 w-full rounded-md border border-border bg-background px-2 text-[11px]"
                            value={icon}
                            onChange={(e) =>
                              patchCard({ icon: e.target.value as DashboardKpiIconId })
                            }
                          >
                            {DASHBOARD_KPI_ICONS.map((item) => (
                              <option key={item.id} value={item.id}>
                                {item.label}
                              </option>
                            ))}
                          </select>
                        </div>
                        <div className="space-y-1">
                          <p className="text-[10px] font-medium text-muted-foreground">Layout</p>
                          <select
                            className="h-8 w-full rounded-md border border-border bg-background px-2 text-[11px]"
                            value={cardLayout}
                            onChange={(e) =>
                              patchCard({ layout: e.target.value as DashboardKpiLayout })
                            }
                          >
                            {DASHBOARD_KPI_LAYOUTS.map((item) => (
                              <option key={item.id} value={item.id}>
                                {item.label}
                              </option>
                            ))}
                          </select>
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1">
                          <p className="text-[10px] font-medium text-muted-foreground">Tone</p>
                          <select
                            className="h-8 w-full rounded-md border border-border bg-background px-2 text-[11px]"
                            value={tone}
                            onChange={(e) =>
                              patchCard({ tone: e.target.value as DashboardKpiTone })
                            }
                          >
                            {DASHBOARD_KPI_TONES.map((item) => (
                              <option key={item.id} value={item.id}>
                                {item.label}
                              </option>
                            ))}
                          </select>
                        </div>
                        <div className="space-y-1">
                          <p className="text-[10px] font-medium text-muted-foreground">Label override</p>
                          <Input
                            className="h-8 text-[11px]"
                            value={override.label ?? ""}
                            placeholder={kpi.label}
                            onChange={(e) => patchCard({ label: e.target.value })}
                          />
                        </div>
                      </div>

                      <label className="flex items-center gap-1.5 text-[11px] text-foreground">
                        <Checkbox
                          className="size-3.5"
                          checked={tinted}
                          onCheckedChange={(value) => patchCard({ tinted: value === true })}
                        />
                        Soft tint background
                      </label>
                    </div>
                  ) : null}
                </div>
              );
            })}
          </div>
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

      {kind === "hero_banner" ? (
        <label className="flex items-center gap-2 text-xs text-foreground">
          <Checkbox
            className="size-4"
            checked={settings.showCta === true}
            onCheckedChange={(value) => onSettingsChange({ showCta: value === true })}
          />
          Show CTA button
        </label>
      ) : null}

      <label className="flex items-center gap-2 text-xs text-foreground">
        <Checkbox
          className="size-4"
          checked={
            kind === "hero_banner"
              ? settings.showDescription !== false
              : settings.showDescription === true
          }
          onCheckedChange={(value) => onSettingsChange({ showDescription: value === true })}
        />
        {kind === "hero_banner"
          ? "Show description"
          : isCustom
            ? "Show catalog description"
            : "Show description under title"}
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
