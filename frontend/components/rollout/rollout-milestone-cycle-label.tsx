"use client";

import { MilestonePhaseLabel } from "@/components/help/milestone-phase-label";
import type { RolloutMilestoneCycle } from "@/modules/rollout/types";

type Props = {
  cycle: Pick<RolloutMilestoneCycle, "phase_key" | "label" | "is_custom">;
  className?: string;
};

export function RolloutMilestoneCycleLabel({ cycle, className }: Props) {
  return (
    <span className={className}>
      <MilestonePhaseLabel phaseKey={cycle.phase_key} label={cycle.label} />
      {cycle.is_custom ? (
        <span className="ml-1.5 inline-flex rounded-md border border-border bg-muted/50 px-1.5 py-0.5 align-middle text-[10px] font-medium text-muted-foreground">
          Custom
        </span>
      ) : null}
    </span>
  );
}
