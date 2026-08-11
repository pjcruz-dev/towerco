"use client";

import type { RolloutTimelinePhase } from "@/modules/rollout/types";
import { cn } from "@/lib/utils";

type Props = {
  phase: Pick<RolloutTimelinePhase, "is_custom" | "counts_toward_sla">;
  className?: string;
};

export function RolloutPhaseMetadataBadges({ phase, className }: Props) {
  const showCustom = Boolean(phase.is_custom);
  const offSla = phase.counts_toward_sla === false;

  if (!showCustom && !offSla) {
    return null;
  }

  return (
    <div className={cn("flex flex-wrap items-center gap-1", className)}>
      {showCustom ? (
        <span className="inline-flex rounded-md border border-border bg-muted/50 px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
          Custom
        </span>
      ) : null}
      {offSla ? (
        <span
          className="inline-flex rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
          title="Excluded from post–Day-1 SLA working-day budget"
        >
          Off SLA
        </span>
      ) : null}
    </div>
  );
}
