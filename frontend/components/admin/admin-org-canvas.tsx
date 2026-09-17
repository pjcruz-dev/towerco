"use client";

import { Minus, Plus, RotateCcw } from "lucide-react";
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type PointerEvent as ReactPointerEvent,
  type ReactNode,
} from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const ZOOM_MIN = 0.35;
const ZOOM_MAX = 1.8;
const ZOOM_STEP = 0.1;

type Props = {
  children: ReactNode;
  className?: string;
  /** Reset / re-center when this key changes (e.g. filters or view mode). */
  resetKey?: string;
};

/**
 * Pan + zoom surface for org charts (grab cursor, drag to pan, wheel to zoom).
 * First paint and Reset fit the full chart into the viewport, centered.
 */
export function AdminOrgCanvas({ children, className, resetKey }: Props) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const contentRef = useRef<HTMLDivElement>(null);
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

  const clampScale = (value: number) => Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, value));

  const fitToView = useCallback(() => {
    const viewport = viewportRef.current;
    const content = contentRef.current;
    if (!viewport || !content) {
      return;
    }

    const vw = viewport.clientWidth;
    const vh = viewport.clientHeight;
    const cw = Math.max(content.scrollWidth, content.offsetWidth, 1);
    const ch = Math.max(content.scrollHeight, content.offsetHeight, 1);
    if (vw < 32 || vh < 32) {
      return;
    }

    const padX = 48;
    const padY = 40;
    const nextScale = clampScale(Math.min((vw - padX) / cw, (vh - padY) / ch, 1));
    const ox = (vw - cw * nextScale) / 2;
    const oy = Math.max(20, (vh - ch * nextScale) / 2);

    setScale(Number(nextScale.toFixed(3)));
    setOffset({ x: ox, y: oy });
  }, []);

  useEffect(() => {
    let cancelled = false;
    const run = () => {
      if (!cancelled) {
        fitToView();
      }
    };
    // Two frames so tree layout (especially wide branches) settles before measuring.
    const frame = requestAnimationFrame(() => {
      requestAnimationFrame(run);
    });
    const timeout = window.setTimeout(run, 120);
    return () => {
      cancelled = true;
      cancelAnimationFrame(frame);
      window.clearTimeout(timeout);
    };
  }, [fitToView, resetKey]);

  // React's onWheel is passive — preventDefault needs a native non-passive listener.
  useEffect(() => {
    const node = viewportRef.current;
    if (!node) return;

    const onWheel = (event: WheelEvent) => {
      event.preventDefault();
      const delta = event.deltaY > 0 ? -ZOOM_STEP : ZOOM_STEP;
      setScale((current) => clampScale(Number((current + delta).toFixed(2))));
    };

    node.addEventListener("wheel", onWheel, { passive: false });
    return () => node.removeEventListener("wheel", onWheel);
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
        <Button type="button" size="icon-xs" variant="ghost" aria-label="Center and fit view" onClick={fitToView}>
          <RotateCcw className="size-3.5" />
        </Button>
      </div>

      <div
        ref={viewportRef}
        className={cn(
          "max-h-[min(75vh,52rem)] overflow-hidden touch-none select-none",
          dragging ? "cursor-grabbing" : "cursor-grab",
        )}
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={endDrag}
        onPointerCancel={endDrag}
      >
        <div
          ref={contentRef}
          className="inline-block w-max max-w-none px-4 py-6"
          style={{
            transform: `translate(${offset.x}px, ${offset.y}px) scale(${scale})`,
            transformOrigin: "0 0",
          }}
        >
          {children}
        </div>
      </div>
      <p className="border-t border-border px-4 py-1.5 text-[11px] text-muted-foreground">
        Drag to pan · scroll to zoom · reset centers and fits the chart
      </p>
    </div>
  );
}
