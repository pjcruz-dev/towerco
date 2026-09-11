"use client";

import { useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { ChevronDown, ChevronRight, GripVertical, Plus, Settings2, X } from "lucide-react";
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type PointerEvent as ReactPointerEvent,
  type ReactNode,
} from "react";

import { DashboardAddWidgetPicker } from "@/components/dashboard/dashboard-add-widget-picker";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import type {
  DashboardCatalogEntry,
  DashboardWidgetSpan,
} from "@/lib/ui/dashboard-widget-catalog";
import {
  colUnitsForSpan,
  nearestAllowedSpan,
} from "@/lib/ui/dashboard-layout-mutations";
import { spanClassName } from "@/lib/ui/dashboard-widget-registry";
import { cn } from "@/lib/utils";

const SPAN_CHIP: Array<{ id: DashboardWidgetSpan; label: string }> = [
  { id: "full", label: "Full" },
  { id: "half", label: "½" },
  { id: "third", label: "⅓" },
  { id: "quarter", label: "¼" },
];

type Props = {
  id: string;
  editing: boolean;
  dataHelp?: string;
  children: ReactNode;
  className?: string;
  span: DashboardWidgetSpan;
  allowedSpans?: DashboardWidgetSpan[];
  minHeight?: number;
  collapsed?: boolean;
  removable?: boolean;
  onSpanChange?: (span: DashboardWidgetSpan) => void;
  onMinHeightChange?: (minHeightPx: number | null) => void;
  onCollapsedChange?: (collapsed: boolean) => void;
  onRemove?: () => void;
  /** Insert a catalog widget before this one (overlay — does not break the 12-col grid). */
  onInsertBefore?: {
    entries: DashboardCatalogEntry[];
    onSelect: (entry: DashboardCatalogEntry) => void;
  };
  /** Inline Layout & options panel (shown via gear in Customize) */
  optionsPanel?: ReactNode;
};

type ResizeAxis = "width" | "height";

export function DashboardSortableWidget({
  id,
  editing,
  dataHelp,
  children,
  className,
  span,
  allowedSpans = ["full", "half", "third", "quarter"],
  minHeight,
  collapsed = false,
  removable = true,
  onSpanChange,
  onMinHeightChange,
  onCollapsedChange,
  onRemove,
  onInsertBefore,
  optionsPanel,
}: Props) {
  const shellRef = useRef<HTMLDivElement | null>(null);
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
    disabled: !editing,
  });

  const [liveSpan, setLiveSpan] = useState(span);
  const [liveMinHeight, setLiveMinHeight] = useState<number | undefined>(minHeight);
  const [insertOpen, setInsertOpen] = useState(false);
  const liveSpanRef = useRef(span);
  const liveMinHeightRef = useRef(minHeight);
  const resizingRef = useRef(false);
  const resizeRef = useRef<{
    axis: ResizeAxis;
    startX: number;
    startY: number;
    startUnits: number;
    startHeight: number;
    colWidth: number;
  } | null>(null);

  useEffect(() => {
    if (resizingRef.current) return;
    setLiveSpan(span);
    liveSpanRef.current = span;
  }, [span]);

  useEffect(() => {
    if (resizingRef.current) return;
    setLiveMinHeight(minHeight);
    liveMinHeightRef.current = minHeight;
  }, [minHeight]);

  const setRefs = useCallback(
    (node: HTMLDivElement | null) => {
      shellRef.current = node;
      setNodeRef(node);
    },
    [setNodeRef],
  );

  useEffect(() => {
    if (!editing) return;

    const onMove = (event: PointerEvent) => {
      const state = resizeRef.current;
      if (!state) return;

      if (state.axis === "width") {
        const deltaCols = Math.round((event.clientX - state.startX) / state.colWidth);
        const nextUnits = Math.max(3, Math.min(12, state.startUnits + deltaCols));
        const nextSpan = nearestAllowedSpan(nextUnits, allowedSpans);
        liveSpanRef.current = nextSpan;
        setLiveSpan(nextSpan);
      }

      if (state.axis === "height") {
        const next = Math.max(120, Math.round(state.startHeight + (event.clientY - state.startY)));
        liveMinHeightRef.current = next;
        setLiveMinHeight(next);
      }
    };

    const onUp = () => {
      const state = resizeRef.current;
      if (!state) return;
      if (state.axis === "width") {
        onSpanChange?.(liveSpanRef.current);
      }
      if (state.axis === "height") {
        onMinHeightChange?.(liveMinHeightRef.current ?? null);
      }
      resizeRef.current = null;
      resizingRef.current = false;
    };

    window.addEventListener("pointermove", onMove);
    window.addEventListener("pointerup", onUp);
    window.addEventListener("pointercancel", onUp);
    return () => {
      window.removeEventListener("pointermove", onMove);
      window.removeEventListener("pointerup", onUp);
      window.removeEventListener("pointercancel", onUp);
    };
  }, [allowedSpans, editing, onMinHeightChange, onSpanChange]);

  const beginResize = (axis: ResizeAxis, event: ReactPointerEvent<HTMLButtonElement>) => {
    event.preventDefault();
    event.stopPropagation();
    const node = shellRef.current;
    if (!node) return;
    const grid = node.parentElement;
    const gridWidth = grid?.clientWidth || node.clientWidth;
    const colWidth = Math.max(24, gridWidth / 12);
    resizingRef.current = true;
    resizeRef.current = {
      axis,
      startX: event.clientX,
      startY: event.clientY,
      startUnits: colUnitsForSpan(liveSpanRef.current),
      startHeight: liveMinHeightRef.current ?? node.clientHeight,
      colWidth,
    };
  };

  const applySpan = (next: DashboardWidgetSpan) => {
    liveSpanRef.current = next;
    setLiveSpan(next);
    onSpanChange?.(next);
  };

  const displaySpan = editing ? liveSpan : span;
  const displayHeight = collapsed ? undefined : editing ? liveMinHeight : minHeight;
  const widthChips = SPAN_CHIP.filter((item) => allowedSpans.includes(item.id));

  return (
    <div
      ref={setRefs}
      data-help={dataHelp}
      data-span={displaySpan}
      style={{
        transform: CSS.Transform.toString(transform),
        transition: resizingRef.current ? undefined : transition,
        minHeight: displayHeight,
      }}
      className={cn(
        "relative min-w-0",
        spanClassName(displaySpan),
        isDragging && "z-20 opacity-90",
        editing && "rounded-xl ring-1 ring-sky-500/25",
        editing && isDragging && "ring-2 ring-sky-500/40",
        className,
      )}
    >
      {editing ? (
        <>
          <button
            type="button"
            className={cn(
              "absolute top-2 left-2 z-20 flex h-7 w-7 cursor-grab items-center justify-center rounded-md",
              "border border-border/80 bg-card/95 text-muted-foreground shadow-sm backdrop-blur-sm",
              "hover:bg-muted active:cursor-grabbing",
            )}
            aria-label="Drag to reorder widget"
            {...attributes}
            {...listeners}
          >
            <GripVertical className="h-3.5 w-3.5" aria-hidden />
          </button>

          {onCollapsedChange ? (
            <button
              type="button"
              className={cn(
                "absolute top-2 left-10 z-20 flex h-7 w-7 items-center justify-center rounded-md",
                "border border-border/80 bg-card/95 text-muted-foreground shadow-sm backdrop-blur-sm",
                "hover:bg-muted",
              )}
              aria-label={collapsed ? "Expand widget" : "Collapse widget"}
              aria-expanded={!collapsed}
              onClick={(event) => {
                event.preventDefault();
                event.stopPropagation();
                onCollapsedChange(!collapsed);
              }}
            >
              {collapsed ? (
                <ChevronRight className="h-3.5 w-3.5" aria-hidden />
              ) : (
                <ChevronDown className="h-3.5 w-3.5" aria-hidden />
              )}
            </button>
          ) : null}

          {onInsertBefore ? (
            <Popover open={insertOpen} onOpenChange={setInsertOpen}>
              <PopoverTrigger
                render={
                  <button
                    type="button"
                    className={cn(
                      "absolute top-2 z-20 flex h-7 w-7 items-center justify-center rounded-md",
                      onCollapsedChange ? "left-[4.75rem]" : "left-10",
                      "border border-border/80 bg-card/95 text-muted-foreground shadow-sm backdrop-blur-sm",
                      "hover:bg-muted",
                    )}
                    aria-label="Insert widget before this one"
                  >
                    <Plus className="h-3.5 w-3.5" aria-hidden />
                  </button>
                }
              />
              <PopoverContent className="w-[26rem] p-2" align="start">
                <p className="mb-1 px-1 text-xs font-medium text-muted-foreground">
                  Insert before this widget
                </p>
                <p className="mb-2 px-1 text-[10px] leading-snug text-muted-foreground">
                  Mini preview shows layout shape · blue line is when to use it
                </p>
                <DashboardAddWidgetPicker
                  entries={onInsertBefore.entries}
                  onSelect={(entry) => {
                    onInsertBefore.onSelect(entry);
                    setInsertOpen(false);
                  }}
                />
              </PopoverContent>
            </Popover>
          ) : null}

          {removable && onRemove ? (
            <button
              type="button"
              className={cn(
                "absolute top-2 right-2 z-20 flex h-7 w-7 items-center justify-center rounded-md",
                "border border-border/80 bg-card/95 text-muted-foreground shadow-sm backdrop-blur-sm",
                "hover:border-destructive/40 hover:bg-destructive/10 hover:text-destructive",
              )}
              aria-label="Remove widget"
              onClick={(event) => {
                event.preventDefault();
                event.stopPropagation();
                onRemove();
              }}
            >
              <X className="h-3.5 w-3.5" aria-hidden />
            </button>
          ) : null}

          {optionsPanel ? (
            <Popover>
              <PopoverTrigger
                render={
                  <button
                    type="button"
                    className={cn(
                      "absolute top-2 z-20 flex h-7 w-7 items-center justify-center rounded-md",
                      removable && onRemove ? "right-10" : "right-2",
                      "border border-border/80 bg-card/95 text-muted-foreground shadow-sm backdrop-blur-sm",
                      "hover:bg-muted",
                    )}
                    aria-label="Widget options"
                  >
                    <Settings2 className="h-3.5 w-3.5" aria-hidden />
                  </button>
                }
              />
              <PopoverContent
                className="w-[min(32rem,calc(100vw-1.5rem))] max-h-[min(85vh,46rem)] overflow-y-auto p-3"
                align="end"
              >
                <p className="mb-2 text-xs font-medium text-muted-foreground">Layout & options</p>
                {optionsPanel}
              </PopoverContent>
            </Popover>
          ) : null}

          {onSpanChange && widthChips.length > 1 ? (
            <div className="absolute top-2 right-20 z-20 flex max-w-[calc(100%-11rem)] flex-wrap items-center justify-end gap-0.5 rounded-md border border-border/80 bg-card/95 p-0.5 shadow-sm backdrop-blur-sm">
              {widthChips.map((item) => (
                <Button
                  key={item.id}
                  type="button"
                  size="sm"
                  variant={displaySpan === item.id ? "secondary" : "ghost"}
                  className="h-6 px-1.5 text-[10px]"
                  onClick={(event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    applySpan(item.id);
                  }}
                >
                  {item.label}
                </Button>
              ))}
            </div>
          ) : null}

          {!collapsed && onSpanChange ? (
            <button
              type="button"
              aria-label="Drag to resize width"
              title="Drag to resize width"
              className="group/resize absolute top-8 bottom-8 -right-1 z-20 flex w-4 cursor-ew-resize items-center justify-center"
              onPointerDown={(event) => beginResize("width", event)}
            >
              <span className="h-8 w-1 rounded-full bg-border group-hover/resize:bg-sky-500/70" />
            </button>
          ) : null}

          {!collapsed && onMinHeightChange ? (
            <button
              type="button"
              aria-label="Drag to resize height"
              title="Drag to resize height"
              className="group/resize-h absolute -bottom-1 right-8 left-8 z-20 flex h-4 cursor-ns-resize items-center justify-center"
              onPointerDown={(event) => beginResize("height", event)}
            >
              <span className="h-1 w-8 rounded-full bg-border group-hover/resize-h:bg-sky-500/70" />
            </button>
          ) : null}

          {onSpanChange || onMinHeightChange ? (
            <div className="pointer-events-none absolute right-2.5 bottom-2.5 z-10 rounded bg-background/80 px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-muted-foreground">
              {displaySpan}
              {collapsed ? " · collapsed" : displayHeight ? ` · ${Math.round(displayHeight)}px` : ""}
            </div>
          ) : null}
        </>
      ) : null}

      {collapsed && !editing ? (
        <button
          type="button"
          className="flex w-full items-center gap-2 rounded-xl border border-border bg-card px-4 py-3 text-left text-sm text-muted-foreground hover:bg-muted/40"
          onClick={() => onCollapsedChange?.(false)}
        >
          <ChevronRight className="h-3.5 w-3.5 shrink-0" aria-hidden />
          <span>Collapsed — click to expand</span>
        </button>
      ) : collapsed && editing ? (
        <div
          className={cn(
            "rounded-xl border border-dashed border-border bg-muted/20 px-4 py-6 text-center text-xs text-muted-foreground",
            "mt-9",
          )}
        >
          Collapsed — click expand to show body
        </div>
      ) : (
        <div className={cn("h-full min-w-0", editing && "px-2 pt-9 pb-3")}>{children}</div>
      )}
    </div>
  );
}
