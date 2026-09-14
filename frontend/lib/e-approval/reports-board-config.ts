/**
 * Declarative E-Forms Reports board — single source of truth for
 * Customize / Add widget / default layout. Add or tweak entries here;
 * catalog + page slots are derived automatically.
 */

import type {
  DashboardCatalogEntry,
  DashboardWidgetCategory,
  DashboardWidgetKind,
  DashboardWidgetSpan,
} from "@/lib/ui/dashboard-widget-catalog";
import type { DashboardDataSourceId } from "@/lib/ui/dashboard-widget-data";

export const REPORTS_BOARD_LAYOUT_KEY = "toweros.e-approval.reports.dashboard.layout.v2";

/** Legacy mono-widget id from Phase 10 — expanded on load. */
export const REPORTS_LEGACY_ANALYTICS_ID = "reports_analytics";

export type ReportsAnalyticsSectionId =
  | "filters"
  | "kpis"
  | "trend"
  | "status"
  | "top_forms"
  | "aging"
  | "bottlenecks"
  | "approver_load"
  | "cycle_time"
  | "rejections";

export type ReportsBoardGroup = "analytics" | "exports";

export type ReportsBoardWidgetConfig = {
  id: string;
  label: string;
  description: string;
  category: DashboardWidgetCategory;
  kind: DashboardWidgetKind;
  defaultSpan: DashboardWidgetSpan;
  allowedSpans: DashboardWidgetSpan[];
  /** Shown on first visit when layout prefs are empty. */
  defaultEnabled: boolean;
  /** Requires e-approval audit permission. */
  requiresAudit: boolean;
  group: ReportsBoardGroup;
  /**
   * Custom page slot section. When set with kind "custom", page supplies render.
   * Chart/KPI kinds omit this and render from normalized analytics data.
   */
  analyticsSection?: ReportsAnalyticsSectionId;
  /** Default Layout & options data source for kind widgets. */
  defaultDataSource?: DashboardDataSourceId;
};

/**
 * Edit this list to reconfigure the Reports Customize board.
 * Order = default widget order.
 *
 * Chart/KPI kinds get full Layout & options (data source, max items, sort).
 * Custom kinds keep interactive builders (filters / export / saved / recent).
 */
export const REPORTS_BOARD_WIDGETS: readonly ReportsBoardWidgetConfig[] = [
  {
    id: "reports_analytics_filters",
    label: "Analytics filters",
    description: "Date range, form, subsidiary, and department filters for analytics.",
    category: "operations",
    kind: "custom",
    defaultSpan: "full",
    allowedSpans: ["full"],
    defaultEnabled: true,
    requiresAudit: true,
    group: "analytics",
    analyticsSection: "filters",
  },
  {
    id: "reports_analytics_kpis",
    label: "Analytics KPIs",
    description: "Volume, cycle, and SLA KPI strip with drill-downs.",
    category: "summary",
    kind: "kpi_metric_row",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    defaultEnabled: true,
    requiresAudit: true,
    group: "analytics",
    defaultDataSource: "kpis",
  },
  {
    id: "reports_analytics_trend",
    label: "Submissions over time",
    description: "Line chart of submissions across the selected period.",
    category: "charts",
    kind: "chart_line",
    defaultSpan: "half",
    allowedSpans: ["full", "half"],
    defaultEnabled: true,
    requiresAudit: true,
    group: "analytics",
    defaultDataSource: "queue",
  },
  {
    id: "reports_analytics_status",
    label: "By status",
    description: "Status mix donut with linked chips.",
    category: "charts",
    kind: "chart_donut",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    defaultEnabled: true,
    requiresAudit: true,
    group: "analytics",
    defaultDataSource: "status",
  },
  {
    id: "reports_analytics_top_forms",
    label: "Top forms",
    description: "Highest volume forms in the selected period.",
    category: "charts",
    kind: "chart_bar_horizontal",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    defaultEnabled: true,
    requiresAudit: true,
    group: "analytics",
    defaultDataSource: "category",
  },
  {
    id: "reports_analytics_aging",
    label: "Approval aging",
    description: "Pending approvals by age versus SLA.",
    category: "charts",
    kind: "chart_bar",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    defaultEnabled: true,
    requiresAudit: true,
    group: "analytics",
    defaultDataSource: "department",
  },
  {
    id: "reports_analytics_bottlenecks",
    label: "Bottleneck steps",
    description: "Pending count by workflow step.",
    category: "charts",
    kind: "chart_bar_horizontal",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    defaultEnabled: false,
    requiresAudit: true,
    group: "analytics",
    defaultDataSource: "subsidiary",
  },
  {
    id: "reports_analytics_approver_load",
    label: "Approver load",
    description: "Open approvals by assignee.",
    category: "lists",
    kind: "list_leaderboard",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    defaultEnabled: false,
    requiresAudit: true,
    group: "analytics",
    defaultDataSource: "people",
  },
  {
    id: "reports_analytics_cycle_time",
    label: "Cycle time",
    description: "Average durations for the selected period.",
    category: "lists",
    kind: "list_activity",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    defaultEnabled: false,
    requiresAudit: true,
    group: "analytics",
    defaultDataSource: "audit",
  },
  {
    id: "reports_analytics_rejections",
    label: "Rejection reasons",
    description: "Top free-text remarks on rejected approvals.",
    category: "lists",
    kind: "list_activity",
    defaultSpan: "half",
    allowedSpans: ["full", "half", "third"],
    defaultEnabled: false,
    requiresAudit: true,
    group: "analytics",
    defaultDataSource: "activity",
  },
  {
    id: "reports_export_builder",
    label: "Export builder",
    description: "Ad-hoc submission export and save-as-report.",
    category: "operations",
    kind: "custom",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    defaultEnabled: true,
    requiresAudit: false,
    group: "exports",
  },
  {
    id: "reports_saved",
    label: "Saved reports",
    description: "Saved report definitions and schedules.",
    category: "lists",
    kind: "custom",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    defaultEnabled: true,
    requiresAudit: false,
    group: "exports",
  },
  {
    id: "reports_recent",
    label: "Recent exports",
    description: "Export download history for your account.",
    category: "operations",
    kind: "custom",
    defaultSpan: "full",
    allowedSpans: ["full", "half"],
    defaultEnabled: true,
    requiresAudit: false,
    group: "exports",
  },
] as const;

export function reportsBoardWidgetsForUser(canAudit: boolean): ReportsBoardWidgetConfig[] {
  return REPORTS_BOARD_WIDGETS.filter((widget) => !widget.requiresAudit || canAudit);
}

export function reportsBoardDefaultEnabledIds(canAudit: boolean): string[] {
  return reportsBoardWidgetsForUser(canAudit)
    .filter((widget) => widget.defaultEnabled)
    .map((widget) => widget.id);
}

export function reportsBoardCatalogEntries(): DashboardCatalogEntry[] {
  return REPORTS_BOARD_WIDGETS.map((widget) => ({
    id: widget.id,
    kind: widget.kind,
    label: widget.label,
    description: widget.description,
    category: widget.category,
    hazeSource: "shared" as const,
    defaultSpan: widget.defaultSpan,
    allowedSpans: [...widget.allowedSpans],
    defaultSettings: widget.defaultDataSource
      ? { dataSource: widget.defaultDataSource, limit: 8, sort: "desc" }
      : undefined,
    modules: ["e-approval" as const],
  }));
}

export function reportsBoardConfigById(id: string): ReportsBoardWidgetConfig | undefined {
  const base = id.includes("~") ? id.slice(0, id.indexOf("~")) : id;
  return REPORTS_BOARD_WIDGETS.find((widget) => widget.id === base);
}

/** True when the page must supply a custom render slot. */
export function reportsBoardNeedsCustomSlot(config: ReportsBoardWidgetConfig): boolean {
  return config.kind === "custom";
}

/** Resolve Layout & options chrome for a Reports board widget. */
export function resolveReportsWidgetChrome(
  widgetId: string,
  options?: {
    title?: string;
    settings?: {
      showDescription?: boolean;
      compact?: boolean;
      [key: string]: string | number | boolean | undefined;
    };
  },
): {
  title: string;
  /** undefined = component default; null = hide; string = override */
  description: string | null | undefined;
  compact: boolean;
} {
  const config = reportsBoardConfigById(widgetId);
  const title = options?.title?.trim() || config?.label || widgetId;
  const showDescription = options?.settings?.showDescription;
  let description: string | null | undefined;
  if (showDescription === true) {
    description = config?.description;
  } else if (showDescription === false) {
    description = null;
  } else {
    description = undefined;
  }
  return {
    title,
    description,
    compact: options?.settings?.compact === true,
  };
}

/**
 * Expand legacy `reports_analytics` and drop unknown ids so prefs stay valid
 * after config changes.
 */
export function resolveReportsEnabledWidgetIds(
  storedEnabledIds: string[],
  canAudit: boolean,
): string[] {
  const available = new Set(reportsBoardWidgetsForUser(canAudit).map((widget) => widget.id));
  const defaults = reportsBoardDefaultEnabledIds(canAudit);

  if (storedEnabledIds.length === 0) {
    return defaults;
  }

  const expanded: string[] = [];
  for (const id of storedEnabledIds) {
    if (id === REPORTS_LEGACY_ANALYTICS_ID) {
      for (const analyticsId of reportsBoardWidgetsForUser(canAudit)
        .filter((widget) => widget.group === "analytics" && widget.defaultEnabled)
        .map((widget) => widget.id)) {
        if (!expanded.includes(analyticsId)) expanded.push(analyticsId);
      }
      continue;
    }
    const base = id.includes("~") ? id.slice(0, id.indexOf("~")) : id;
    if (available.has(base) || available.has(id)) {
      expanded.push(id);
    }
  }

  return expanded.length > 0 ? expanded : defaults;
}

export function reportsAddableCatalogEntries(
  enabledIds: string[],
  canAudit: boolean,
  group?: ReportsBoardGroup,
): DashboardCatalogEntry[] {
  const enabled = new Set(enabledIds);
  return reportsBoardCatalogEntries().filter((entry) => {
    const config = reportsBoardConfigById(entry.id);
    if (!config) return false;
    if (config.requiresAudit && !canAudit) return false;
    if (group && config.group !== group) return false;
    return !enabled.has(entry.id);
  });
}

/** True when a board widget id belongs to the Analytics or Exports hub tab. */
export function reportsWidgetBelongsToGroup(widgetId: string, group: ReportsBoardGroup): boolean {
  const config = reportsBoardConfigById(widgetId);
  // Page-enhancement / unbound widgets sit on Exports (operational tools).
  if (!config) return group === "exports";
  return config.group === group;
}

/**
 * Merge hub-tab UI edits into the full enabled list so Analytics Customize
 * does not drop Exports widgets (and vice versa).
 */
export function mergeReportsEnabledIdsForTab(
  previousFull: string[],
  nextFromTabUi: string[],
  activeTab: ReportsBoardGroup,
): string[] {
  const other = previousFull.filter((id) => !reportsWidgetBelongsToGroup(id, activeTab));
  const nextTab = nextFromTabUi.filter((id) => reportsWidgetBelongsToGroup(id, activeTab));
  const merged: string[] = [];
  let tabInserted = false;
  for (const id of previousFull) {
    if (reportsWidgetBelongsToGroup(id, activeTab)) {
      if (!tabInserted) {
        merged.push(...nextTab);
        tabInserted = true;
      }
      continue;
    }
    if (!merged.includes(id)) merged.push(id);
  }
  if (!tabInserted) {
    for (const id of nextTab) {
      if (!merged.includes(id)) merged.push(id);
    }
  }
  for (const id of other) {
    if (!merged.includes(id)) merged.push(id);
  }
  return merged;
}

/** Merge drag-reorder within one hub tab into the full widget order. */
export function mergeReportsOrderForTab(
  previousFull: string[],
  nextTabOrder: string[],
  activeTab: ReportsBoardGroup,
): string[] {
  return mergeReportsEnabledIdsForTab(previousFull, nextTabOrder, activeTab);
}
