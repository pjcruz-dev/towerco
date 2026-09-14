"use client";

import {
  resolveDashboardSkeletonSlots,
  type DashboardSkeletonKindHint,
  type DashboardSkeletonSlot,
} from "@/lib/ui/dashboard-board-skeleton";
import { SPAN_CLASS } from "@/lib/ui/dashboard-widget-catalog";
import type { DashboardLayoutPrefs } from "@/lib/ui/dashboard-widget-registry";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

function SlotBody({ kindHint }: { kindHint: DashboardSkeletonKindHint }) {
  switch (kindHint) {
    case "kpi":
      return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {Array.from({ length: 4 }).map((_, index) => (
            <div
              key={`kpi-${index}`}
              className="rounded-lg border border-border/80 bg-background/60 p-3"
            >
              <Skeleton className="h-3 w-16" />
              <Skeleton className="mt-3 h-7 w-12" />
              <Skeleton className="mt-2 h-3 w-24" />
            </div>
          ))}
        </div>
      );
    case "filters":
      return (
        <div className="space-y-3">
          <div className="flex flex-wrap gap-2">
            {Array.from({ length: 4 }).map((_, index) => (
              <Skeleton key={`chip-${index}`} className="h-8 w-24 rounded-md" />
            ))}
          </div>
          <Skeleton className="h-9 w-full max-w-sm rounded-md" />
        </div>
      );
    case "table":
      return (
        <div className="space-y-0 divide-y divide-border overflow-hidden rounded-lg border border-border/80">
          {Array.from({ length: 6 }).map((_, index) => (
            <div
              key={`row-${index}`}
              className="flex items-center justify-between gap-3 px-3 py-2.5"
            >
              <Skeleton className="h-4 w-36" />
              <Skeleton className="h-4 w-16" />
            </div>
          ))}
        </div>
      );
    case "chart":
      return (
        <div className="space-y-3">
          <Skeleton className="h-3 w-40" />
          <Skeleton className="h-40 w-full rounded-lg" />
        </div>
      );
    case "list":
      return (
        <div className="space-y-2">
          {Array.from({ length: 4 }).map((_, index) => (
            <div
              key={`list-${index}`}
              className="rounded-lg border border-border/80 px-3 py-2.5"
            >
              <Skeleton className="h-4 w-40 max-w-full" />
              <Skeleton className="mt-2 h-3 w-40" />
            </div>
          ))}
        </div>
      );
    case "attention":
      return (
        <div className="space-y-2">
          <Skeleton className="h-4 w-48" />
          <Skeleton className="h-3 w-64 max-w-full" />
          <div className="mt-3 flex flex-wrap gap-2">
            <Skeleton className="h-8 w-28 rounded-md" />
            <Skeleton className="h-8 w-24 rounded-md" />
          </div>
        </div>
      );
    case "shortcuts":
      return (
        <div className="flex flex-wrap gap-2">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={`shortcut-${index}`} className="h-9 w-28 rounded-md" />
          ))}
        </div>
      );
    default:
      return (
        <div className="space-y-2">
          <Skeleton className="h-4 w-32" />
          <Skeleton className="h-3 w-full max-w-md" />
          <Skeleton className="mt-3 h-24 w-full rounded-lg" />
        </div>
      );
  }
}

function SkeletonCard({ slot }: { slot: DashboardSkeletonSlot }) {
  return (
    <div
      className={cn(
        SPAN_CLASS[slot.span],
        "rounded-xl border border-border bg-card p-4 shadow-sm",
      )}
      style={slot.minHeightPx ? { minHeight: slot.minHeightPx } : undefined}
    >
      <div className="mb-3 flex items-center justify-between gap-2">
        <Skeleton className="h-4 w-28" />
        <Skeleton className="h-3 w-12" />
      </div>
      <SlotBody kindHint={slot.kindHint} />
    </div>
  );
}

type Props = {
  layout: DashboardLayoutPrefs;
  defaultEnabledIds: string[];
  className?: string;
  /** Optional override when slots already computed (tests / reuse). */
  slots?: DashboardSkeletonSlot[];
};

/**
 * Loading placeholder that mirrors Customize layout (order, spans, section shapes).
 */
export function DashboardBoardSkeleton({
  layout,
  defaultEnabledIds,
  className,
  slots: slotsProp,
}: Props) {
  const slots =
    slotsProp ??
    resolveDashboardSkeletonSlots({
      layout,
      defaultEnabledIds,
    });

  if (slots.length === 0) {
    return (
      <div
        className={cn("rounded-xl border border-dashed border-border bg-muted/20 px-4 py-10", className)}
        role="status"
        aria-busy="true"
        aria-label="Loading dashboard"
      >
        <Skeleton className="mx-auto h-4 w-40" />
      </div>
    );
  }

  return (
    <div
      className={cn("grid grid-cols-12 items-start gap-4 md:gap-5", className)}
      role="status"
      aria-busy="true"
      aria-label="Loading dashboard"
    >
      {slots.map((slot) => (
        <SkeletonCard key={slot.id} slot={slot} />
      ))}
    </div>
  );
}
