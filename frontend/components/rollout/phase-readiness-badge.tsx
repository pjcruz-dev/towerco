"use client";

import { phaseGateReadiness, type PhaseReadinessTone } from "@/lib/rollout/phase-gate-readiness";
import type { RolloutDetail, RolloutTimelinePhase } from "@/modules/rollout/types";
import { cn } from "@/lib/utils";

const toneClass: Record<PhaseReadinessTone, string> = {
  success: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200",
  warning: "bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200",
  danger: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200",
  info: "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200",
  neutral: "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200",
};

export function PhaseReadinessBadge({ phase, detail }: { phase: RolloutTimelinePhase; detail: RolloutDetail }) {
  const readiness = phaseGateReadiness(phase, detail);
  if (!readiness) {
    return null;
  }

  return (
    <span
      className={cn(
        "inline-flex max-w-full truncate rounded-full px-2 py-0.5 text-[11px] font-medium",
        toneClass[readiness.tone],
      )}
      title={readiness.label}
    >
      {readiness.label}
    </span>
  );
}
