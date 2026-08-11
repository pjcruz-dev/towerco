"use client";

import * as React from "react";

import { useIsMobile } from "@/hooks/use-mobile";

function subscribeFinePointer(callback: () => void) {
  const media = window.matchMedia("(hover: hover) and (pointer: fine)");
  media.addEventListener("change", callback);
  return () => media.removeEventListener("change", callback);
}

function getFinePointerSnapshot() {
  return window.matchMedia("(hover: hover) and (pointer: fine)").matches;
}

/** True when the primary input is mouse/trackpad (hover tooltips). SSR assumes fine pointer. */
export function usePrefersFinePointer(): boolean {
  return React.useSyncExternalStore(subscribeFinePointer, getFinePointerSnapshot, () => true);
}

/** Narrow phone / small tablet — matches app sidebar breakpoint (768px). */
export function useIsNarrowViewport(): boolean {
  return useIsMobile();
}
