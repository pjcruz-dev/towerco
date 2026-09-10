"use client";

import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  type DragEndEvent,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  rectSortingStrategy,
} from "@dnd-kit/sortable";
import { useMemo } from "react";

import { DashboardInsertBetween } from "@/components/dashboard/dashboard-insert-between";
import { DashboardSortableWidget } from "@/components/dashboard/dashboard-sortable-widget";
import { DashboardWidgetOptionsPanel } from "@/components/dashboard/dashboard-widget-options-panel";
import {
  getCatalogEntry,
  type DashboardCatalogEntry,
  type DashboardWidgetSpan,
} from "@/lib/ui/dashboard-widget-catalog";
import {
  availableDataSources,
  type DashboardNormalizedData,
} from "@/lib/ui/dashboard-widget-data";
import {
  defaultWidgetOrderIds,
  isWidgetRemovable,
  resolveDashboardOrderIds,
  resolveVisibleOrderedWidgets,
  resolveWidgetSpan,
  type DashboardLayoutPrefs,
  type DashboardWidgetDef,
} from "@/lib/ui/dashboard-widget-registry";
import { resolveWidgetMinHeight } from "@/lib/ui/dashboard-layout-mutations";
import { cn } from "@/lib/utils";

type Props<TId extends string> = {
  widgets: DashboardWidgetDef<TId>[];
  order: string[];
  hiddenIds: string[];
  enabledIds?: string[];
  layoutPrefs?: DashboardLayoutPrefs;
  editing: boolean;
  /** Catalog entries available to insert */
  addableCatalog?: DashboardCatalogEntry[];
  data?: DashboardNormalizedData;
  onOrderChange: (nextOrder: string[]) => void;
  onSpanChange?: (widgetId: string, span: DashboardWidgetSpan) => void;
  onMinHeightChange?: (widgetId: string, minHeightPx: number | null) => void;
  onRemove?: (widgetId: string) => void;
  onDuplicate?: (widgetId: string) => void;
  onAddWidget?: (entry: DashboardCatalogEntry, insertAt: number) => void;
  onTitleChange?: (widgetId: string, title: string) => void;
  onSettingsChange?: (
    widgetId: string,
    patch: Record<string, string | number | boolean | undefined>,
  ) => void;
  className?: string;
};

export function DashboardWidgetBoard<TId extends string>({
  widgets,
  order,
  hiddenIds,
  enabledIds = [],
  layoutPrefs = {
    widgetOrder: [],
    hiddenWidgetIds: [],
    enabledWidgetIds: [],
    spans: {},
    widgetOptions: {},
  },
  editing,
  addableCatalog = [],
  data,
  onOrderChange,
  onSpanChange,
  onMinHeightChange,
  onRemove,
  onDuplicate,
  onAddWidget,
  onTitleChange,
  onSettingsChange,
  className,
}: Props<TId>) {
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const visible = useMemo(
    () => resolveVisibleOrderedWidgets(widgets, order, hiddenIds, enabledIds),
    [widgets, order, hiddenIds, enabledIds],
  );

  const visibleIds = useMemo(() => visible.map((widget) => widget.id), [visible]);
  const dataSources = useMemo(() => (data ? availableDataSources(data) : ["auto" as const]), [data]);
  const hasAddable = addableCatalog.length > 0;
  const canInsert = Boolean(editing && onAddWidget && hasAddable);

  const onDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;

    const fullOrder = resolveDashboardOrderIds(widgets, order);
    const from = fullOrder.indexOf(String(active.id));
    const to = fullOrder.indexOf(String(over.id));
    if (from < 0 || to < 0) return;

    onOrderChange(arrayMove(fullOrder, from, to));
  };

  if (visible.length === 0) {
    return (
      <div className="space-y-3">
        <p className="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-8 text-center text-sm text-muted-foreground">
          No widgets on this dashboard yet.
        </p>
        {canInsert ? (
          <DashboardInsertBetween
            entries={addableCatalog}
            label="Add first widget"
            onSelect={(entry) => onAddWidget?.(entry, 0)}
          />
        ) : null}
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {editing ? (
        <p className="text-xs text-muted-foreground">
          Drag grip to reorder · width chips pack widgets side-by-side
          {canInsert ? " · + inserts a widget · " : " · "}
          gear for options
          {onRemove ? " · × removes (re-add from + Add widget when available)" : ""}
        </p>
      ) : null}

      {canInsert ? (
        <DashboardInsertBetween
          entries={addableCatalog}
          label="Add widget at top"
          onSelect={(entry) => onAddWidget?.(entry, 0)}
        />
      ) : null}

      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
        <SortableContext items={visibleIds} strategy={rectSortingStrategy}>
          {/*
            Widgets are direct grid children only — never insert full-width rails between them,
            or fractional widths cannot share a row.
          */}
          <div className={cn("grid grid-cols-12 items-start gap-4 md:gap-5", className)}>
            {visible.map((widget, index) => {
              const span = resolveWidgetSpan(widget.id, layoutPrefs, widget.defaultSpan ?? "full");
              const entry = getCatalogEntry(widget.id);
              const allowedSpans = entry?.allowedSpans ?? ["full", "half", "third", "quarter"];
              const minHeight = resolveWidgetMinHeight(layoutPrefs, widget.id);
              const removable = isWidgetRemovable(widget);
              const options = layoutPrefs.widgetOptions[widget.id];
              const collapsed = options?.settings?.collapsed === true;

              return (
                <DashboardSortableWidget
                  key={widget.id}
                  id={widget.id}
                  editing={editing}
                  dataHelp={widget.dataHelp}
                  span={span}
                  allowedSpans={allowedSpans}
                  minHeight={minHeight}
                  collapsed={collapsed}
                  removable={removable}
                  onSpanChange={
                    onSpanChange ? (next) => onSpanChange(widget.id, next) : undefined
                  }
                  onMinHeightChange={
                    onMinHeightChange
                      ? (next) => onMinHeightChange(widget.id, next)
                      : undefined
                  }
                  onCollapsedChange={
                    onSettingsChange
                      ? (next) => onSettingsChange(widget.id, { collapsed: next || undefined })
                      : undefined
                  }
                  onRemove={onRemove && removable ? () => onRemove(widget.id) : undefined}
                  onInsertBefore={
                    canInsert
                      ? {
                          entries: addableCatalog,
                          onSelect: (entry) => onAddWidget?.(entry, index),
                        }
                      : undefined
                  }
                  optionsPanel={
                    editing && onTitleChange && onSettingsChange && onSpanChange ? (
                      <DashboardWidgetOptionsPanel
                        widgetId={widget.id}
                        label={widget.label}
                        entry={entry}
                        options={options}
                        span={span}
                        dataSources={dataSources}
                        kpis={data?.kpis}
                        removable={removable}
                        onTitleChange={(title) => onTitleChange(widget.id, title)}
                        onSpanChange={(next) => onSpanChange(widget.id, next)}
                        onSettingsChange={(patch) => onSettingsChange(widget.id, patch)}
                        onDuplicate={onDuplicate ? () => onDuplicate(widget.id) : undefined}
                        onRemove={onRemove && removable ? () => onRemove(widget.id) : undefined}
                      />
                    ) : undefined
                  }
                >
                  {widget.render()}
                </DashboardSortableWidget>
              );
            })}
          </div>
        </SortableContext>
      </DndContext>

      {canInsert ? (
        <DashboardInsertBetween
          entries={addableCatalog}
          label="Add widget"
          onSelect={(entry) => onAddWidget?.(entry, visible.length)}
        />
      ) : null}
    </div>
  );
}

export function ensureDashboardOrder(widgets: Array<{ id: string }>, order: string[]): string[] {
  if (order.length === 0) return defaultWidgetOrderIds(widgets);
  return resolveDashboardOrderIds(widgets, order);
}
