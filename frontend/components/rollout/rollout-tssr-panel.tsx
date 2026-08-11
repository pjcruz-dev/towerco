"use client";

import {
  isDayOneReady,
  isDayOneSet,
  isMocColPassed,
  isPreAssessmentPassed,
  isTssrCreationPassed,
  isTssrCreationReady,
} from "@/lib/rollout/phase-gate-readiness";
import type { RolloutDetail } from "@/modules/rollout/types";

type Props = {
  detail: RolloutDetail;
  phaseKey: string;
};

/**
 * TSSR create/review and TSSR MNO / Day-1 guidance panel.
 */
export function RolloutTssrPanel({ detail, phaseKey }: Props) {
  const creationPassed = isTssrCreationPassed(detail);
  const dayOneSet = isDayOneSet(detail);
  const ready = isTssrCreationReady(detail);

  return (
    <div className="space-y-3">
      {!ready ? (
        <div className="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
          {!isPreAssessmentPassed(detail)
            ? "Complete Pre-assessment before TSSR."
            : !isMocColPassed(detail)
              ? "Complete MOC/COL before TSSR."
              : "Complete earlier phases before TSSR."}
        </div>
      ) : null}

      <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <h3 className="text-sm font-medium text-foreground">
          {phaseKey === "tssr_mno_approval" ? "TSSR MNO approval / Day-1" : "TSSR create & review"}
        </h3>
        <p className="mt-1 text-sm text-muted-foreground">
          {phaseKey === "tssr_creation"
            ? "Engineering reviews the TSSR package (gate: SAQ Eng → SAQ → PMO). Then record Day-1."
            : "Record the TSSR approved date in the Day-1 card above to start the delivery SLA and pass this gate."}
        </p>
        <ul className="mt-2 list-inside list-disc text-xs text-muted-foreground">
          <li>Pre-assessment + MOC/COL complete {ready ? "✓" : ""}</li>
          <li>TSSR create/review {creationPassed ? "✓" : "— request Engineering gate"}</li>
          <li>Day-1 / TSSR MNO {dayOneSet ? "✓" : isDayOneReady(detail) ? "— ready to record" : ""}</li>
        </ul>
        {dayOneSet ? (
          <p className="mt-2 text-xs text-green-800 dark:text-green-200">
            Day-1 complete — post–Day-1 phases (Pre-con → Construction) are now on the SLA clock.
          </p>
        ) : null}
      </div>
    </div>
  );
}
