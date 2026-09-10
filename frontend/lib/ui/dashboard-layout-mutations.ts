import type { DashboardWidgetSpan } from "@/lib/ui/dashboard-widget-catalog";
import { getCatalogEntry } from "@/lib/ui/dashboard-widget-catalog";
import type { DashboardLayoutPrefs } from "@/lib/ui/dashboard-widget-registry";
import { catalogBaseId, nextDuplicateWidgetId } from "@/lib/ui/dashboard-layout-presets";

export { catalogBaseId, nextDuplicateWidgetId } from "@/lib/ui/dashboard-layout-presets";
export {
  applyLayoutPreset,
  duplicateWidgetInLayout,
  DASHBOARD_LAYOUT_PRESETS,
  type DashboardLayoutPresetId,
} from "@/lib/ui/dashboard-layout-presets";

export const SPAN_COL_UNITS: Record<DashboardWidgetSpan, number> = {
  quarter: 3,
  third: 4,
  half: 6,
  full: 12,
};

const UNIT_TO_SPAN: Array<{ units: number; span: DashboardWidgetSpan }> = [
  { units: 3, span: "quarter" },
  { units: 4, span: "third" },
  { units: 6, span: "half" },
  { units: 12, span: "full" },
];

export function colUnitsForSpan(span: DashboardWidgetSpan): number {
  return SPAN_COL_UNITS[span];
}

export function nearestAllowedSpan(
  targetUnits: number,
  allowed: DashboardWidgetSpan[],
): DashboardWidgetSpan {
  const candidates = UNIT_TO_SPAN.filter((row) => allowed.includes(row.span));
  if (candidates.length === 0) return "full";
  let best = candidates[0]!;
  let bestDist = Math.abs(best.units - targetUnits);
  for (const row of candidates.slice(1)) {
    const dist = Math.abs(row.units - targetUnits);
    if (dist < bestDist) {
      best = row;
      bestDist = dist;
    }
  }
  return best.span;
}

export function applyWidgetSpan(
  layout: DashboardLayoutPrefs,
  widgetId: string,
  span: DashboardWidgetSpan,
): DashboardLayoutPrefs {
  return {
    ...layout,
    spans: { ...layout.spans, [widgetId]: span },
    widgetOptions: {
      ...layout.widgetOptions,
      [widgetId]: { ...layout.widgetOptions[widgetId], span },
    },
  };
}

export function applyWidgetMinHeight(
  layout: DashboardLayoutPrefs,
  widgetId: string,
  minHeightPx: number | null,
): DashboardLayoutPrefs {
  const prev = layout.widgetOptions[widgetId] ?? {};
  const settings = { ...(prev.settings ?? {}) };
  if (minHeightPx == null || minHeightPx <= 0) {
    delete settings.minHeight;
  } else {
    settings.minHeight = Math.round(minHeightPx);
  }
  return {
    ...layout,
    widgetOptions: {
      ...layout.widgetOptions,
      [widgetId]: { ...prev, settings },
    },
  };
}

export function resolveWidgetMinHeight(
  layout: Pick<DashboardLayoutPrefs, "widgetOptions">,
  widgetId: string,
): number | undefined {
  const value = layout.widgetOptions[widgetId]?.settings?.minHeight;
  return typeof value === "number" && value > 0 ? value : undefined;
}

export function applyWidgetSettings(
  layout: DashboardLayoutPrefs,
  widgetId: string,
  settingsPatch: Record<string, string | number | boolean | undefined>,
): DashboardLayoutPrefs {
  const prev = layout.widgetOptions[widgetId] ?? {};
  const nextSettings = { ...(prev.settings ?? {}) };
  for (const [key, value] of Object.entries(settingsPatch)) {
    if (value === undefined || value === "") {
      delete nextSettings[key];
    } else {
      nextSettings[key] = value;
    }
  }
  return {
    ...layout,
    widgetOptions: {
      ...layout.widgetOptions,
      [widgetId]: { ...prev, settings: nextSettings },
    },
  };
}

export function insertWidgetIntoLayout(
  layout: DashboardLayoutPrefs,
  widgetId: string,
  defaultSpan: DashboardWidgetSpan,
  insertAt: number,
  currentOrder: string[],
  defaultEnabledIds: string[],
): DashboardLayoutPrefs {
  const enabled =
    layout.enabledWidgetIds.length > 0 ? [...layout.enabledWidgetIds] : [...defaultEnabledIds];
  const baseOrder = currentOrder.length > 0 ? [...currentOrder] : [...enabled];
  const id = nextDuplicateWidgetId(widgetId, [...enabled, ...baseOrder]);
  if (!enabled.includes(id)) enabled.push(id);

  const without = baseOrder.filter((existing) => existing !== id);
  const at = Math.max(0, Math.min(insertAt, without.length));
  without.splice(at, 0, id);

  return {
    ...layout,
    enabledWidgetIds: enabled,
    widgetOrder: without,
    spans: { ...layout.spans, [id]: layout.spans[id] ?? defaultSpan },
  };
}

export function removeWidgetFromLayout(
  layout: DashboardLayoutPrefs,
  widgetId: string,
  defaultEnabledIds: string[],
): DashboardLayoutPrefs {
  const catalog = getCatalogEntry(catalogBaseId(widgetId));
  if (catalog?.removable === false || catalog?.hideable === false) {
    return layout;
  }

  const enabled =
    layout.enabledWidgetIds.length > 0 ? layout.enabledWidgetIds : defaultEnabledIds;
  return {
    ...layout,
    enabledWidgetIds: enabled.filter((id) => id !== widgetId),
    widgetOrder: layout.widgetOrder.filter((id) => id !== widgetId),
    hiddenWidgetIds: layout.hiddenWidgetIds.filter((id) => id !== widgetId),
    spans: Object.fromEntries(Object.entries(layout.spans).filter(([id]) => id !== widgetId)),
    widgetOptions: Object.fromEntries(
      Object.entries(layout.widgetOptions).filter(([id]) => id !== widgetId),
    ),
  };
}
