import type { ReactNode } from "react";

import {
  SPAN_CLASS,
  getCatalogEntry,
  type DashboardWidgetOptions,
  type DashboardWidgetSpan,
} from "@/lib/ui/dashboard-widget-catalog";
import type { PageChromePrefs } from "@/lib/ui/page-chrome-config";
import { applyWidgetOrder, resolveWidgetOrderIds } from "@/lib/ui/widget-layout-order";

export type DashboardWidgetDef<TId extends string = string> = {
  id: TId;
  label: string;
  /** Default true. When false, widget stays visible and cannot be soft-hidden. */
  hideable?: boolean;
  /** Default true. When false, cannot remove from enabled set. */
  removable?: boolean;
  dataHelp?: string;
  defaultSpan?: DashboardWidgetSpan;
  render: () => ReactNode;
};

export type DashboardLayoutPrefs = {
  widgetOrder: string[];
  hiddenWidgetIds: string[];
  /** Explicit catalog selection. Empty = all available defs enabled (legacy). */
  enabledWidgetIds: string[];
  /** Per-widget span overrides */
  spans: Record<string, DashboardWidgetSpan>;
  /** Per-widget options (title, settings) */
  widgetOptions: Record<string, DashboardWidgetOptions>;
  /** Page title / description / header action visibility */
  pageChrome?: PageChromePrefs;
};

export const EMPTY_DASHBOARD_LAYOUT_PREFS: DashboardLayoutPrefs = {
  widgetOrder: [],
  hiddenWidgetIds: [],
  enabledWidgetIds: [],
  spans: {},
  widgetOptions: {},
  pageChrome: {},
};

function isStringArray(value: unknown): value is string[] {
  return Array.isArray(value) && value.every((id) => typeof id === "string");
}

function isSpan(value: unknown): value is DashboardWidgetSpan {
  return value === "full" || value === "half" || value === "third" || value === "quarter";
}

export function isDashboardLayoutPrefs(value: unknown): value is DashboardLayoutPrefs {
  if (!value || typeof value !== "object") return false;
  const row = value as Record<string, unknown>;
  if ("widgetOrder" in row && !isStringArray(row.widgetOrder)) return false;
  if ("hiddenWidgetIds" in row && !isStringArray(row.hiddenWidgetIds)) return false;
  if ("enabledWidgetIds" in row && !isStringArray(row.enabledWidgetIds)) return false;
  if ("spans" in row && row.spans !== undefined) {
    if (!row.spans || typeof row.spans !== "object" || Array.isArray(row.spans)) return false;
    for (const span of Object.values(row.spans as Record<string, unknown>)) {
      if (!isSpan(span)) return false;
    }
  }
  if ("widgetOptions" in row && row.widgetOptions !== undefined) {
    if (!row.widgetOptions || typeof row.widgetOptions !== "object" || Array.isArray(row.widgetOptions)) {
      return false;
    }
  }
  return true;
}

export function normalizeDashboardLayoutPrefs(value: unknown): DashboardLayoutPrefs {
  if (!isDashboardLayoutPrefs(value)) {
    return { ...EMPTY_DASHBOARD_LAYOUT_PREFS, spans: {}, widgetOptions: {} };
  }
  const row = value as Record<string, unknown>;
  const spansRaw =
    row.spans && typeof row.spans === "object" && !Array.isArray(row.spans)
      ? (row.spans as Record<string, unknown>)
      : {};
  const spans: Record<string, DashboardWidgetSpan> = {};
  for (const [key, span] of Object.entries(spansRaw)) {
    if (isSpan(span)) spans[key] = span;
  }

  const optionsRaw =
    row.widgetOptions && typeof row.widgetOptions === "object" && !Array.isArray(row.widgetOptions)
      ? (row.widgetOptions as Record<string, unknown>)
      : {};
  const widgetOptions: Record<string, DashboardWidgetOptions> = {};
  for (const [key, opt] of Object.entries(optionsRaw)) {
    if (!opt || typeof opt !== "object" || Array.isArray(opt)) continue;
    const o = opt as Record<string, unknown>;
    widgetOptions[key] = {
      title: typeof o.title === "string" ? o.title : undefined,
      span: isSpan(o.span) ? o.span : undefined,
      settings:
        o.settings && typeof o.settings === "object" && !Array.isArray(o.settings)
          ? (o.settings as Record<string, string | number | boolean>)
          : undefined,
    };
  }

  return {
    widgetOrder: isStringArray(row.widgetOrder) ? row.widgetOrder : [],
    hiddenWidgetIds: isStringArray(row.hiddenWidgetIds) ? row.hiddenWidgetIds : [],
    enabledWidgetIds: isStringArray(row.enabledWidgetIds) ? row.enabledWidgetIds : [],
    spans,
    widgetOptions,
    pageChrome: normalizePageChrome(row.pageChrome),
  };
}

function normalizePageChrome(value: unknown): PageChromePrefs {
  if (!value || typeof value !== "object" || Array.isArray(value)) return {};
  const row = value as Record<string, unknown>;
  const placement = row.identityPlacement;
  return {
    title: typeof row.title === "string" ? row.title : undefined,
    description: typeof row.description === "string" ? row.description : undefined,
    hiddenActionIds: isStringArray(row.hiddenActionIds) ? row.hiddenActionIds : undefined,
    actionOrder: isStringArray(row.actionOrder) ? row.actionOrder : undefined,
    identityPlacement:
      placement === "start" ||
      placement === "end" ||
      placement === "above" ||
      placement === "below"
        ? placement
        : undefined,
  };
}

export function isWidgetHideable(widget: Pick<DashboardWidgetDef, "hideable">): boolean {
  return widget.hideable !== false;
}

export function isWidgetRemovable(widget: Pick<DashboardWidgetDef, "id" | "removable" | "hideable">): boolean {
  if (widget.removable === false) return false;
  if (widget.hideable === false) return false;
  const catalog = getCatalogEntry(String(widget.id ?? ""));
  if (catalog?.removable === false) return false;
  if (catalog?.hideable === false) return false;
  return true;
}

/**
 * Filter by enabled catalog selection (empty enabled = all available),
 * apply order, then drop soft-hidden hideable widgets.
 */
export function resolveVisibleOrderedWidgets<T extends DashboardWidgetDef>(
  widgets: T[],
  order: string[],
  hiddenIds: string[],
  enabledIds: string[] = [],
): T[] {
  const enabled =
    enabledIds.length === 0
      ? widgets
      : widgets.filter((widget) => {
          if (enabledIds.includes(widget.id)) return true;
          // Always keep non-removable even if prefs stale
          return widget.removable === false || widget.hideable === false;
        });

  const hidden = new Set(hiddenIds);
  const ordered = applyWidgetOrder(enabled, order);
  return ordered.filter((widget) => {
    if (!isWidgetHideable(widget)) return true;
    return !hidden.has(widget.id);
  });
}

export function resolveWidgetSpan(
  widgetId: string,
  prefs: Pick<DashboardLayoutPrefs, "spans" | "widgetOptions">,
  fallback: DashboardWidgetSpan = "full",
): DashboardWidgetSpan {
  return prefs.widgetOptions[widgetId]?.span ?? prefs.spans[widgetId] ?? getCatalogEntry(widgetId)?.defaultSpan ?? fallback;
}

export function spanClassName(span: DashboardWidgetSpan): string {
  return SPAN_CLASS[span];
}

export function defaultWidgetOrderIds(widgets: Array<{ id: string }>): string[] {
  return widgets.map((widget) => widget.id);
}

export function resolveDashboardOrderIds(
  widgets: Array<{ id: string }>,
  savedOrder: string[],
): string[] {
  return resolveWidgetOrderIds(
    widgets.map((widget) => widget.id),
    savedOrder,
  );
}
