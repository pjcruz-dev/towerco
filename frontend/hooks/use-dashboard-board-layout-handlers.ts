"use client";

import { useMemo } from "react";

import type { DashboardLayoutUpdater } from "@/hooks/use-dashboard-layout-prefs";
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

/**
 * Board mutation handlers. Always apply against the latest layout via functional
 * setLayout — Card appearance / options fire many rapid patches that would otherwise
 * clobber each other through a stale `layout` closure.
 */
export function useDashboardBoardLayoutHandlers(
  _layout: DashboardLayoutPrefs,
  setLayout: (next: DashboardLayoutUpdater) => void,
  defaultEnabledIds: string[],
) {
  return useMemo(
    () => ({
      onOrderChange: (nextOrder: string[]) => {
        setLayout((current) => ({ ...current, widgetOrder: nextOrder }));
      },
      onSpanChange: (widgetId: string, span: DashboardWidgetSpan) => {
        setLayout((current) => applyWidgetSpan(current, widgetId, span));
      },
      onMinHeightChange: (widgetId: string, minHeightPx: number | null) => {
        setLayout((current) => applyWidgetMinHeight(current, widgetId, minHeightPx));
      },
      onRemove: (widgetId: string) => {
        setLayout((current) => removeWidgetFromLayout(current, widgetId, defaultEnabledIds));
      },
      onDuplicate: (widgetId: string) => {
        setLayout((current) => {
          const currentOrder =
            current.widgetOrder.length > 0
              ? current.widgetOrder
              : current.enabledWidgetIds.length > 0
                ? current.enabledWidgetIds
                : defaultEnabledIds;
          return duplicateWidgetInLayout(current, widgetId, currentOrder, defaultEnabledIds);
        });
      },
      onAddWidget: (entry: DashboardCatalogEntry, insertAt: number) => {
        setLayout((current) => {
          const currentOrder =
            current.widgetOrder.length > 0
              ? current.widgetOrder
              : current.enabledWidgetIds.length > 0
                ? current.enabledWidgetIds
                : defaultEnabledIds;
          return insertWidgetIntoLayout(
            current,
            entry.id,
            entry.defaultSpan,
            insertAt,
            currentOrder,
            defaultEnabledIds,
          );
        });
      },
      onTitleChange: (widgetId: string, title: string) => {
        setLayout((current) => ({
          ...current,
          widgetOptions: {
            ...current.widgetOptions,
            [widgetId]: { ...current.widgetOptions[widgetId], title },
          },
        }));
      },
      onSettingsChange: (
        widgetId: string,
        patch: Record<string, string | number | boolean | undefined>,
      ) => {
        setLayout((current) => applyWidgetSettings(current, widgetId, patch));
      },
      onApplyPreset: (
        preset: DashboardLayoutPresetId,
        orderedIds: string[],
        options?: { defaultEnabledIds?: string[]; availableIds?: string[] },
      ) => {
        setLayout((current) => applyLayoutPreset(current, preset, orderedIds, options));
      },
    }),
    [defaultEnabledIds, setLayout],
  );
}
