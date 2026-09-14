import type { ReactNode } from "react";

import { DashboardKindWidget } from "@/components/dashboard/widgets/dashboard-kind-widget";
import {
  catalogEntriesForModule,
  getCatalogEntry,
  type DashboardCatalogEntry,
} from "@/lib/ui/dashboard-widget-catalog";
import {
  dataSatisfiesKind,
  resolveCatalogSlotId,
  type DashboardModuleId,
  type DashboardNormalizedData,
} from "@/lib/ui/dashboard-widget-data";
import { catalogBaseId } from "@/lib/ui/dashboard-layout-presets";
import type { DashboardWidgetDef } from "@/lib/ui/dashboard-widget-registry";

/** Tour anchors for primary KPI strips rendered via catalog kind (not page slots). */
const MODULE_KPI_DATA_HELP: Partial<Record<DashboardModuleId, string>> = {
  ticketing: "tk-overview-kpis",
  "e-approval": "ea-overview-kpis",
};

export type DynamicBoardBuildInput = {
  moduleId: DashboardModuleId;
  /** Live normalized metrics for kind-based widgets */
  data: DashboardNormalizedData;
  /**
   * Page-owned interactive widgets (tables, filter bars, custom charts).
   * These always win over kind defaults / aliases.
   */
  slots: DashboardWidgetDef[];
  enabledIds: string[];
  titleOverrides?: Record<string, string | undefined>;
  widgetOptions?: Record<string, import("@/lib/ui/dashboard-widget-catalog").DashboardWidgetOptions>;
};

function slotMap(slots: DashboardWidgetDef[]): Map<string, DashboardWidgetDef> {
  return new Map(slots.map((widget) => [widget.id, widget] as const));
}

function withTitle(widget: DashboardWidgetDef, title?: string): DashboardWidgetDef {
  if (!title?.trim()) return widget;
  const original = widget.render;
  return {
    ...widget,
    label: title.trim(),
    render: () => original(),
  };
}

function kindDef(
  entry: DashboardCatalogEntry,
  data: DashboardNormalizedData,
  title?: string,
  options?: import("@/lib/ui/dashboard-widget-catalog").DashboardWidgetOptions,
  instanceId?: string,
  dataHelp?: string,
): DashboardWidgetDef {
  return {
    id: instanceId ?? entry.id,
    label: title?.trim() || entry.label,
    hideable: entry.hideable !== false,
    removable: entry.removable !== false,
    defaultSpan: entry.defaultSpan,
    dataHelp,
    render: () => (
      <DashboardKindWidget entry={entry} data={data} title={title} options={options} />
    ),
  };
}

/**
 * Whether a catalog entry can render live data for this module (slot or kind+data).
 */
export function isCatalogEntryDataBound(
  moduleId: DashboardModuleId,
  entry: DashboardCatalogEntry,
  slots: Array<Pick<DashboardWidgetDef, "id">>,
  data: DashboardNormalizedData,
): boolean {
  const byId = new Set(slots.map((s) => s.id));
  if (byId.has(entry.id)) return true;
  const alias = resolveCatalogSlotId(moduleId, entry.id);
  if (alias !== entry.id && byId.has(alias)) return true;
  if (entry.bindMode === "always") return true;
  // Native ops widgets that must be page slots
  if (
    entry.kind === "alerts" ||
    entry.kind === "filters" ||
    entry.kind === "table" ||
    entry.kind === "custom"
  ) {
    return byId.has(entry.id) || byId.has(alias);
  }
  return dataSatisfiesKind(entry.kind, data);
}

export function bindableCatalogEntries(
  moduleId: DashboardModuleId,
  slots: Array<Pick<DashboardWidgetDef, "id">>,
  data: DashboardNormalizedData,
): DashboardCatalogEntry[] {
  return catalogEntriesForModule(moduleId).filter((entry) =>
    isCatalogEntryDataBound(moduleId, entry, slots, data),
  );
}

/**
 * Build the board from enabled catalog ids using:
 * 1) exact slot, 2) aliased slot, 3) kind renderer on live data.
 * Never returns unbound placeholders for bindable entries.
 */
export function buildDynamicBoardWidgets({
  moduleId,
  data,
  slots,
  enabledIds,
  titleOverrides = {},
  widgetOptions = {},
}: DynamicBoardBuildInput): DashboardWidgetDef[] {
  const bySlot = slotMap(slots);
  const catalog = catalogEntriesForModule(moduleId);
  const defaultIds = slots.map((widget) => widget.id);
  const ids = enabledIds.length > 0 ? enabledIds : defaultIds;

  const merged: DashboardWidgetDef[] = [];
  const seen = new Set<string>();

  const push = (widget: DashboardWidgetDef) => {
    if (seen.has(widget.id)) return;
    seen.add(widget.id);
    merged.push(widget);
  };

  for (const id of ids) {
    const title = titleOverrides[id] ?? widgetOptions[id]?.title;
    const baseId = catalogBaseId(id);

    const exactSlot = bySlot.get(id) ?? (baseId !== id ? undefined : bySlot.get(baseId));
    if (exactSlot) {
      push(withTitle(exactSlot, title));
      continue;
    }

    const baseSlot = bySlot.get(baseId);
    if (baseSlot && baseId !== id) {
      push({
        ...baseSlot,
        id,
        label: title?.trim() || `${baseSlot.label} (copy)`,
        render: baseSlot.render,
      });
      continue;
    }

    const aliasId = resolveCatalogSlotId(moduleId, baseId);
    const aliased = bySlot.get(aliasId);
    if (aliased) {
      push({
        ...aliased,
        id,
        label: title?.trim() || getCatalogEntry(id)?.label || aliased.label,
        render: aliased.render,
      });
      continue;
    }

    const entry = getCatalogEntry(id) ?? catalog.find((item) => item.id === baseId);
    if (entry && dataSatisfiesKind(entry.kind, data)) {
      const help =
        (entry.id === "kpis" || baseId === "kpis") && MODULE_KPI_DATA_HELP[moduleId]
          ? MODULE_KPI_DATA_HELP[moduleId]
          : undefined;
      push(kindDef(entry, data, title, widgetOptions[id], id, help));
      continue;
    }
  }

  if (enabledIds.length === 0) {
    for (const widget of slots) {
      if (!seen.has(widget.id)) push(widget);
    }
  }

  return merged;
}

/** @deprecated Prefer buildDynamicBoardWidgets — kept for gradual migration. */
export function mergeCatalogBoardWidgets(
  bound: DashboardWidgetDef[],
  enabledIds: string[],
  moduleId: DashboardModuleId,
  titleOverrides: Record<string, string | undefined> = {},
  data?: DashboardNormalizedData,
): DashboardWidgetDef[] {
  if (data) {
    return buildDynamicBoardWidgets({
      moduleId,
      data,
      slots: bound,
      enabledIds,
      titleOverrides,
    });
  }
  // Legacy path without data: keep bound only; do not invent unbound shells.
  const byId = new Map(bound.map((widget) => [widget.id, widget] as const));
  const ids = enabledIds.length > 0 ? enabledIds : bound.map((w) => w.id);
  const merged: DashboardWidgetDef[] = [];
  const seen = new Set<string>();
  for (const id of ids) {
    if (seen.has(id)) continue;
    const widget = byId.get(id);
    if (!widget) continue;
    seen.add(id);
    const title = titleOverrides[id];
    merged.push(title?.trim() ? { ...widget, label: title.trim() } : widget);
  }
  if (enabledIds.length === 0) {
    for (const widget of bound) {
      if (!seen.has(widget.id)) merged.push(widget);
    }
  }
  return merged;
}

export function renderNode(node: ReactNode): ReactNode {
  return node;
}
