"use client";

import { buildTimelineContextStrip, type PhaseReadinessTone } from "@/lib/rollout/phase-gate-readiness";
import type { RolloutDetail } from "@/modules/rollout/types";
import { cn } from "@/lib/utils";

type Props = {
  detail: RolloutDetail;
};

const toneClass: Record<PhaseReadinessTone, string> = {
  success: "border-green-200 bg-green-50 text-green-900 dark:border-green-900 dark:bg-green-950/50 dark:text-green-100",
  warning: "border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-100",
  danger: "border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/50 dark:text-red-100",
  info: "border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-100",
  neutral: "border-border bg-muted/40 text-foreground",
};

export function RolloutTimelineContextStrip({ detail }: Props) {
  const items = buildTimelineContextStrip(detail);
  if (items.length === 0) {
    return null;
  }

  return (
    <div className="flex flex-wrap gap-2 rounded-lg border border-border bg-card px-3 py-2 shadow-sm">
      <span className="self-center text-xs font-medium text-muted-foreground">At a glance</span>
      {items.map((item) => (
        <div
          key={item.id}
          className={cn(
            "rounded-md border px-2.5 py-1 text-xs",
            toneClass[item.tone ?? "neutral"],
          )}
        >
          <span className="font-medium">{item.label}:</span> {item.value}
        </div>
      ))}
    </div>
  );
}
