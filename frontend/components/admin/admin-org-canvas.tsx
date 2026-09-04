"use client";

import { Minus, Plus, RotateCcw } from "lucide-react";
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type PointerEvent as ReactPointerEvent,
  type ReactNode,
  type WheelEvent as ReactWheelEvent,
} from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const ZOOM_MIN = 0.4;
const ZOOM_MAX = 1.8;
const ZOOM_STEP = 0.1;

type Props = {
  children: ReactNode;
  className?: string;
  /** Reset transform when this key changes (e.g. view mode). */
  resetKey?: string;
};

/**
 * Pan + zoom surface for org charts (grab cursor, drag to pan, wheel to zoom).
 */
export function AdminOrgCanvas({ children, className, resetKey }: Props) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const [scale, setScale] = useState(0.9);
  const [offset, setOffset] = useState({ x: 0, y: 0 });
  const dragRef = useRef<{
    pointerId: number;
    startX: number;
    startY: number;
    originX: number;
    originY: number;
  } | null>(null);
  const [dragging, setDragging] = useState(false);

  useEffect(() => {
    setScale(0.9);
    setOffset({ x: 0, y: 0 });
  }, [resetKey]);

  const clampScale = (value: number) => Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, value));

  const onWheel = useCallback((event: ReactWheelEvent<HTMLDivElement>) => {
    event.preventDefault();
    const delta = event.deltaY > 0 ? -ZOOM_STEP : ZOOM_STEP;
    setScale((current) => clampScale(Number((current + delta).toFixed(2))));
  }, []);

  const onPointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
    if (event.button !== 0 && event.button !== 1) return;
    const target = event.target as HTMLElement | null;
    if (target?.closest("button, a, input, select, textarea, [data-org-no-pan]")) {
      return;
    }
    dragRef.current = {
      pointerId: event.pointerId,
      startX: event.clientX,
      startY: event.clientY,
      originX: offset.x,
      originY: offset.y,
    };
    setDragging(true);
    event.currentTarget.setPointerCapture(event.pointerId);
  };

  const onPointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
    const drag = dragRef.current;
    if (!drag || drag.pointerId !== event.pointerId) return;
    setOffset({
      x: drag.originX + (event.clientX - drag.startX),
      y: drag.originY + (event.clientY - drag.startY),
    });
  };

  const endDrag = (event: ReactPointerEvent<HTMLDivElement>) => {
    const drag = dragRef.current;
    if (!drag || drag.pointerId !== event.pointerId) return;
    dragRef.current = null;
    setDragging(false);
    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId);
    }
  };

  return (
    <div className={cn("relative", className)}>
      <div className="absolute right-3 top-3 z-10 flex items-center gap-1 rounded-lg border border-border bg-card/95 p-1 shadow-sm backdrop-blur-sm">
        <Button
          type="button"
          size="icon-xs"
          variant="ghost"
          aria-label="Zoom out"
          disabled={scale <= ZOOM_MIN}
          onClick={() => setScale((s) => clampScale(Number((s - ZOOM_STEP).toFixed(2))))}
        >
          <Minus className="size-3.5" />
        </Button>
        <span className="min-w-[2.75rem] text-center text-[11px] tabular-nums text-muted-foreground">
          {Math.round(scale * 100)}%
        </span>
        <Button
          type="button"
          size="icon-xs"
          variant="ghost"
          aria-label="Zoom in"
          disabled={scale >= ZOOM_MAX}
          onClick={() => setScale((s) => clampScale(Number((s + ZOOM_STEP).toFixed(2))))}
        >
          <Plus className="size-3.5" />
        </Button>
        <Button
          type="button"
          size="icon-xs"
          variant="ghost"
          aria-label="Reset view"
          onClick={() => {
            setScale(0.9);
            setOffset({ x: 0, y: 0 });
          }}
        >
          <RotateCcw className="size-3.5" />
        </Button>
      </div>

      <div
        ref={viewportRef}
        className={cn(
          "max-h-[min(75vh,52rem)] overflow-hidden touch-none select-none",
          dragging ? "cursor-grabbing" : "cursor-grab",
        )}
        onWheel={onWheel}
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={endDrag}
        onPointerCancel={endDrag}
      >
        <div
          className="origin-top px-4 py-6"
          style={{
            transform: `translate(${offset.x}px, ${offset.y}px) scale(${scale})`,
            transformOrigin: "top center",
          }}
        >
          {children}
        </div>
      </div>
      <p className="border-t border-border px-4 py-1.5 text-[11px] text-muted-foreground">
        Drag to pan · scroll to zoom · use controls to reset
      </p>
    </div>
  );
}
