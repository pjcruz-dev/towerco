"use client";

import { useCallback, useEffect, useRef, useState } from "react";

import { TowerOsAssistantMark } from "@/components/assistant/toweros-assistant-mark";
import { StatusBadge } from "@/components/ui/status-badge";
import { useAssistantDrawer } from "@/hooks/use-assistant-drawer";
import { cn } from "@/lib/utils";

type Position = { x: number; y: number };

const STORAGE_KEY = "toweros.assistant.launcher.position";
const BUTTON_SIZE = 56;
const EDGE_MARGIN = 16;
const DRAG_THRESHOLD = 4;

function clampToViewport(pos: Position): Position {
  if (typeof window === "undefined") {
    return pos;
  }
  const maxX = window.innerWidth - BUTTON_SIZE - EDGE_MARGIN;
  const maxY = window.innerHeight - BUTTON_SIZE - EDGE_MARGIN;
  return {
    x: Math.min(Math.max(pos.x, EDGE_MARGIN), Math.max(maxX, EDGE_MARGIN)),
    y: Math.min(Math.max(pos.y, EDGE_MARGIN), Math.max(maxY, EDGE_MARGIN)),
  };
}

function defaultPosition(): Position {
  if (typeof window === "undefined") {
    return { x: 24, y: 24 };
  }
  return {
    x: window.innerWidth - BUTTON_SIZE - 24,
    y: window.innerHeight - BUTTON_SIZE - 24,
  };
}

export function AssistantFloatingLauncher() {
  const { open, setOpen, minimized, activity } = useAssistantDrawer();
  const [position, setPosition] = useState<Position | null>(null);
  const [dragging, setDragging] = useState(false);
  const dragState = useRef<{
    pointerId: number;
    offsetX: number;
    offsetY: number;
    startX: number;
    startY: number;
    moved: boolean;
  } | null>(null);

  useEffect(() => {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored) {
      try {
        const parsed = JSON.parse(stored) as Position;
        if (typeof parsed?.x === "number" && typeof parsed?.y === "number") {
          setPosition(clampToViewport(parsed));
          return;
        }
      } catch {
        // ignore malformed value
      }
    }
    setPosition(defaultPosition());
  }, []);

  useEffect(() => {
    const onResize = () => {
      setPosition((current) => (current ? clampToViewport(current) : current));
    };
    window.addEventListener("resize", onResize);
    return () => window.removeEventListener("resize", onResize);
  }, []);

  const onPointerMove = useCallback((event: PointerEvent) => {
    const state = dragState.current;
    if (!state || event.pointerId !== state.pointerId) {
      return;
    }
    if (
      !state.moved &&
      (Math.abs(event.clientX - state.startX) > DRAG_THRESHOLD ||
        Math.abs(event.clientY - state.startY) > DRAG_THRESHOLD)
    ) {
      state.moved = true;
      setDragging(true);
    }
    if (!state.moved) {
      return;
    }
    setPosition(
      clampToViewport({
        x: event.clientX - state.offsetX,
        y: event.clientY - state.offsetY,
      }),
    );
  }, []);

  const endDrag = useCallback(() => {
    const state = dragState.current;
    dragState.current = null;
    setDragging(false);
    window.removeEventListener("pointermove", onPointerMove);
    window.removeEventListener("pointerup", endDrag);
    setPosition((current) => {
      if (current) {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(current));
      }
      return current;
    });
    return state?.moved ?? false;
  }, [onPointerMove]);

  const onPointerDown = (event: React.PointerEvent<HTMLButtonElement>) => {
    if (event.button !== 0 || !position) {
      return;
    }
    dragState.current = {
      pointerId: event.pointerId,
      offsetX: event.clientX - position.x,
      offsetY: event.clientY - position.y,
      startX: event.clientX,
      startY: event.clientY,
      moved: false,
    };
    window.addEventListener("pointermove", onPointerMove);
    window.addEventListener("pointerup", endDrag);
  };

  const onClick = () => {
    if (dragState.current?.moved) {
      return;
    }
    setOpen(true);
  };

  if ((open && !minimized) || !position) {
    return null;
  }

  return (
    <button
      type="button"
      onPointerDown={onPointerDown}
      onClick={onClick}
      aria-label={
        activity === "working"
          ? "Assistant is still working. Open to watch."
          : activity === "done"
            ? "Assistant finished. Open the reply."
            : "Ask INFRA SUITE"
      }
      aria-busy={activity === "working"}
      title={
        activity === "working"
          ? "Still working"
          : activity === "done"
            ? "Reply is ready"
            : "Ask INFRA SUITE"
      }
      className={cn(
        "fixed z-40 flex h-14 w-14 touch-none items-center justify-center rounded-full",
        "bg-primary text-primary-foreground shadow-lg",
        "transition-shadow hover:bg-primary/90 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
        dragging ? "cursor-grabbing" : "cursor-grab",
        activity === "working" && "animate-pulse",
      )}
      style={{ left: position.x, top: position.y }}
    >
      {activity === "working" ? (
        <span
          className="pointer-events-none absolute -inset-1 animate-spin rounded-full border-2 border-transparent border-t-primary-foreground border-r-primary-foreground/40"
          aria-hidden
        />
      ) : null}
      {activity === "done" ? (
        <StatusBadge
          tone="success"
          className="pointer-events-none absolute -top-2 left-1/2 z-10 -translate-x-1/2 px-1.5 py-0 text-[10px] leading-4 shadow-sm"
        >
          Done
        </StatusBadge>
      ) : null}
      <span className="sr-only" aria-live="polite">
        {activity === "done" ? "Reply is ready." : activity === "working" ? "Assistant is still working." : ""}
      </span>
      <TowerOsAssistantMark className="relative h-6 w-6" />
    </button>
  );
}
