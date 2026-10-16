"use client";

import { GripVertical } from "lucide-react";
import { createContext, useContext, useMemo, type PointerEvent as ReactPointerEvent, type ReactNode } from "react";

import type { DynField } from "@/lib/api/modules/dynamic-entities-api";
import { clampSpan } from "@/lib/dynamic-entities/form-arrange-rows";

type WidthAdjustValue = {
  active: boolean;
  dirty: boolean;
  saving: boolean;
  spanFor: (field: DynField) => number;
  setSpan: (fieldId: string, span: number) => void;
  discard: () => void;
};

const WidthAdjustContext = createContext<WidthAdjustValue | null>(null);

export function useFieldColumnSpan(field: DynField): number {
  const ctx = useContext(WidthAdjustContext);
  return ctx?.spanFor(field) ?? clampSpan(field.column_span ?? 12);
}

export function useWidthAdjust(): WidthAdjustValue | null {
  return useContext(WidthAdjustContext);
}

export function WidthAdjustProvider({
  spans,
  active,
  dirty,
  saving,
  setSpan,
  discard,
  children,
}: {
  spans: Record<string, number>;
  active: boolean;
  dirty: boolean;
  saving: boolean;
  setSpan: (fieldId: string, span: number) => void;
  discard: () => void;
  children: ReactNode;
}) {
  const value = useMemo<WidthAdjustValue>(
    () => ({
      active,
      dirty,
      saving,
      setSpan,
      discard,
      spanFor: (field) => spans[field.id] ?? clampSpan(field.column_span ?? 12),
    }),
    [active, dirty, saving, setSpan, discard, spans],
  );

  return <WidthAdjustContext.Provider value={value}>{children}</WidthAdjustContext.Provider>;
}

/** Snap a pointer on a 12-column grid to a column span, measured from the tile's left edge. */
export function spanFromClientX(grid: HTMLElement, tileLeft: number, clientX: number): number {
  const gap = Number.parseFloat(getComputedStyle(grid).columnGap) || 0;
  const column = (grid.clientWidth - gap * 11) / 12;
  const unit = column + gap;
  if (unit <= 0) return 12;
  return clampSpan(Math.round((clientX - tileLeft + gap / 2) / unit));
}

export function WidthEdgeHandle({
  label,
  span,
  onSpan,
}: {
  label: string;
  span: number;
  onSpan: (span: number) => void;
}) {
  function onPointerDown(event: ReactPointerEvent<HTMLButtonElement>) {
    event.stopPropagation();
    const handle = event.currentTarget;
    handle.focus();
    const tile = handle.closest("[data-dyn-field-tile], [data-dyn-group-tile]");
    const grid = tile?.parentElement?.closest(".grid") ?? handle.closest(".grid");
    if (!(grid instanceof HTMLElement) || !(tile instanceof HTMLElement)) return;
    const tileLeft = tile.getBoundingClientRect().left;
    handle.setPointerCapture(event.pointerId);
    handle.classList.add("border-primary", "text-foreground");
    tile.classList.add("ring-2", "ring-primary/40");
    const move = (ev: PointerEvent) => {
      onSpan(spanFromClientX(grid, tileLeft, ev.clientX));
    };
    const end = () => {
      handle.classList.remove("border-primary", "text-foreground");
      tile.classList.remove("ring-2", "ring-primary/40");
      handle.removeEventListener("pointermove", move);
      handle.removeEventListener("pointerup", end);
      handle.removeEventListener("pointercancel", end);
    };
    handle.addEventListener("pointermove", move);
    handle.addEventListener("pointerup", end);
    handle.addEventListener("pointercancel", end);
  }

  function nudge(delta: number) {
    onSpan(clampSpan(span + delta));
  }

  return (
    <button
      type="button"
      aria-label={`${label} width, ${span} of 12 columns. Drag the edge or use the arrow keys.`}
      title={`${span}/12`}
      className="absolute top-1/2 right-1.5 z-10 flex h-14 w-8 -translate-y-1/2 cursor-ew-resize touch-none flex-col items-center justify-center gap-0.5 rounded-md border border-border bg-card text-muted-foreground shadow-sm hover:border-primary hover:text-foreground focus-visible:border-primary focus-visible:outline-none"
      onPointerDown={onPointerDown}
      onClick={(event) => event.stopPropagation()}
      onKeyDown={(event) => {
        if (event.key === "ArrowRight" || event.key === "ArrowUp") {
          event.preventDefault();
          nudge(1);
        } else if (event.key === "ArrowLeft" || event.key === "ArrowDown") {
          event.preventDefault();
          nudge(-1);
        }
      }}
    >
      <GripVertical className="size-3.5" aria-hidden />
      <span className="font-mono text-[10px] leading-none tabular-nums">{span}/12</span>
    </button>
  );
}
