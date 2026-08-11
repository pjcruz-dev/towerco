"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { MessageCircleQuestion } from "lucide-react";

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
  const { open, setOpen } = useAssistantDrawer();
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

  if (open || !position) {
    return null;
  }

  return (
    <button
      type="button"
      onPointerDown={onPointerDown}
      onClick={onClick}
      aria-label="Ask TowerOS"
      className={cn(
        "fixed z-40 flex h-14 w-14 touch-none items-center justify-center rounded-full",
        "bg-primary text-primary-foreground shadow-lg ring-1 ring-black/5",
        "transition-shadow hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
        dragging ? "cursor-grabbing" : "cursor-grab",
      )}
      style={{ left: position.x, top: position.y }}
    >
      <MessageCircleQuestion className="h-6 w-6" />
    </button>
  );
}
