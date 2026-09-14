import {
  getCatalogEntry,
  type DashboardWidgetKind,
  type DashboardWidgetSpan,
} from "@/lib/ui/dashboard-widget-catalog";
import { catalogBaseId } from "@/lib/ui/dashboard-layout-presets";
import { resolveWidgetMinHeight } from "@/lib/ui/dashboard-layout-mutations";
import {
  resolveWidgetSpan,
  type DashboardLayoutPrefs,
} from "@/lib/ui/dashboard-widget-registry";

export type DashboardSkeletonKindHint =
  | "kpi"
  | "table"
  | "chart"
  | "list"
  | "filters"
  | "attention"
  | "shortcuts"
  | "card";

export type DashboardSkeletonSlot = {
  id: string;
  span: DashboardWidgetSpan;
  kindHint: DashboardSkeletonKindHint;
  minHeightPx: number | null;
};

function kindHintFor(
  widgetId: string,
  kind: DashboardWidgetKind | undefined,
): DashboardSkeletonKindHint {
  const base = catalogBaseId(widgetId);
  if (
    base === "filters" ||
    base === "scope_tabs" ||
    kind === "filters"
  ) {
    return "filters";
  }
  if (
    base === "table" ||
    base === "batch_list" ||
    base === "submissions_table" ||
    base === "template_grid" ||
    kind === "table"
  ) {
    return "table";
  }
  if (
    base === "kpis" ||
    base === "page_kpi_strip" ||
    kind === "kpi_metric_row" ||
    kind === "kpi_single" ||
    kind === "kpi_sparkline" ||
    kind === "kpi_gauge" ||
    kind === "kpi_hero_chart"
  ) {
    return "kpi";
  }
  if (
    base === "page_attention" ||
    base === "alerts" ||
    base === "awaiting_me" ||
    kind === "page_attention" ||
    kind === "alerts"
  ) {
    return "attention";
  }
  if (
    base === "page_shortcuts" ||
    base === "shortcuts" ||
    base === "quick_actions" ||
    kind === "shortcuts"
  ) {
    return "shortcuts";
  }
  if (
    kind?.startsWith("chart_") ||
    base === "action_charts" ||
    base.startsWith("chart_")
  ) {
    return "chart";
  }
  if (
    kind === "list_activity" ||
    kind === "list_progress" ||
    kind === "list_leaderboard" ||
    kind === "list_users" ||
    kind === "bar_list" ||
    base === "recent_activity" ||
    base === "page_activity" ||
    base === "action_queue" ||
    base === "recent_tickets" ||
    base === "queue_awaiting" ||
    base === "queue_attention" ||
    base === "table_category_analytics"
  ) {
    return "list";
  }
  return "card";
}

/**
 * Resolve skeleton placeholders that mirror the user's Customize layout
 * (enabled order + spans + min-heights).
 */
export function resolveDashboardSkeletonSlots(input: {
  layout: DashboardLayoutPrefs;
  defaultEnabledIds: string[];
}): DashboardSkeletonSlot[] {
  const { layout, defaultEnabledIds } = input;
  const enabled =
    layout.enabledWidgetIds.length > 0 ? layout.enabledWidgetIds : defaultEnabledIds;
  const hidden = new Set(layout.hiddenWidgetIds);
  const order =
    layout.widgetOrder.length > 0
      ? layout.widgetOrder
      : enabled.length > 0
        ? enabled
        : defaultEnabledIds;

  const orderedIds: string[] = [];
  const seen = new Set<string>();
  for (const id of order) {
    if (!enabled.includes(id) || hidden.has(id) || seen.has(id)) continue;
    seen.add(id);
    orderedIds.push(id);
  }
  for (const id of enabled) {
    if (hidden.has(id) || seen.has(id)) continue;
    seen.add(id);
    orderedIds.push(id);
  }
  if (orderedIds.length === 0) {
    for (const id of defaultEnabledIds) {
      if (seen.has(id)) continue;
      seen.add(id);
      orderedIds.push(id);
    }
  }

  return orderedIds.map((id) => {
    const entry = getCatalogEntry(id);
    return {
      id,
      span: resolveWidgetSpan(id, layout, entry?.defaultSpan ?? "full"),
      kindHint: kindHintFor(id, entry?.kind),
      minHeightPx: resolveWidgetMinHeight(layout, id),
    };
  });
}
