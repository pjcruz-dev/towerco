"use client";

import { useMemo } from "react";

import type {
  DashboardCatalogEntry,
  DashboardWidgetSpan,
} from "@/lib/ui/dashboard-widget-catalog";
import {
  applyLayoutPreset,
  applyWidgetMinHeight,
  applyWidgetSettings,
  applyWidgetSpan,
  duplicateWidgetInLayout,
  insertWidgetIntoLayout,
  removeWidgetFromLayout,
  type DashboardLayoutPresetId,
} from "@/lib/ui/dashboard-layout-mutations";
import type { DashboardLayoutPrefs } from "@/lib/ui/dashboard-widget-registry";

export function useDashboardBoardLayoutHandlers(
  layout: DashboardLayoutPrefs,
  setLayout: (next: DashboardLayoutPrefs) => void,
  defaultEnabledIds: string[],
) {
  return useMemo(
    () => ({
      onOrderChange: (nextOrder: string[]) => {
        setLayout({ ...layout, widgetOrder: nextOrder });
      },
      onSpanChange: (widgetId: string, span: DashboardWidgetSpan) => {
        setLayout(applyWidgetSpan(layout, widgetId, span));
      },
      onMinHeightChange: (widgetId: string, minHeightPx: number | null) => {
        setLayout(applyWidgetMinHeight(layout, widgetId, minHeightPx));
      },
      onRemove: (widgetId: string) => {
        setLayout(removeWidgetFromLayout(layout, widgetId, defaultEnabledIds));
      },
      onDuplicate: (widgetId: string) => {
        const currentOrder =
          layout.widgetOrder.length > 0
            ? layout.widgetOrder
            : layout.enabledWidgetIds.length > 0
              ? layout.enabledWidgetIds
              : defaultEnabledIds;
        setLayout(duplicateWidgetInLayout(layout, widgetId, currentOrder, defaultEnabledIds));
      },
      onAddWidget: (entry: DashboardCatalogEntry, insertAt: number) => {
        const currentOrder =
          layout.widgetOrder.length > 0
            ? layout.widgetOrder
            : layout.enabledWidgetIds.length > 0
              ? layout.enabledWidgetIds
              : defaultEnabledIds;
        setLayout(
          insertWidgetIntoLayout(
            layout,
            entry.id,
            entry.defaultSpan,
            insertAt,
            currentOrder,
            defaultEnabledIds,
          ),
        );
      },
      onTitleChange: (widgetId: string, title: string) => {
        setLayout({
          ...layout,
          widgetOptions: {
            ...layout.widgetOptions,
            [widgetId]: { ...layout.widgetOptions[widgetId], title },
          },
        });
      },
      onSettingsChange: (
        widgetId: string,
        patch: Record<string, string | number | boolean | undefined>,
      ) => {
        setLayout(applyWidgetSettings(layout, widgetId, patch));
      },
      onApplyPreset: (
        preset: DashboardLayoutPresetId,
        orderedIds: string[],
        options?: { defaultEnabledIds?: string[]; availableIds?: string[] },
      ) => {
        setLayout(applyLayoutPreset(layout, preset, orderedIds, options));
      },
    }),
    [defaultEnabledIds, layout, setLayout],
  );
}
