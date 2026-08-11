"use client";

import Link from "next/link";

import { AcronymLabel } from "@/components/help/acronym-label";
import { buttonVariants } from "@/components/ui/button";
import {
  isBuildReadinessComplete,
  isRfiReady,
  isRfiRecorded,
} from "@/lib/rollout/phase-gate-readiness";
import type { RolloutDetail } from "@/modules/rollout/types";
import { cn } from "@/lib/utils";

type Props = {
  rolloutId: string;
  detail: RolloutDetail;
};

/**
 * P7 — Construction → Record RFI (★ site ready) guidance.
 */
export function RolloutConstructionPanel({ rolloutId, detail }: Props) {
  const p6Done = isBuildReadinessComplete(detail);
  const rfiReady = isRfiReady(detail);
  const rfiDone = isRfiRecorded(detail);

  return (
    <div className="space-y-3">
      {!p6Done ? (
        <div className="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
          Complete <span className="font-medium">build readiness</span> (Pre-con → Permitting → SKOM) before
          Construction / RFI.
        </div>
      ) : null}

      {rfiDone ? (
        <div className="rounded-lg border border-green-200 bg-green-50/80 px-3 py-2 text-sm text-green-950 dark:border-green-900 dark:bg-green-950/30 dark:text-green-100">
          ★ Site ready — <AcronymLabel term="RFI / RFTI">RFI</AcronymLabel>{" "}
          {detail.actual_rfi_date}. Proceed to Site License / Handover.
        </div>
      ) : null}

      <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <h3 className="text-sm font-medium text-foreground">Construction + Energization</h3>
        <p className="mt-1 text-sm text-muted-foreground">
          Build and energize the site. Recording <AcronymLabel term="RFI / RFTI">RFI</AcronymLabel> closes
          delivery SLA and marks <span className="font-medium">site ready</span> (gate: RFI Certificate).
        </p>
        <ul className="mt-3 list-inside list-disc text-xs text-muted-foreground">
          <li>Build readiness {p6Done ? "✓" : ""}</li>
          <li>Daily CME reports during construction</li>
          <li>
            Record RFI ★ site ready
            {rfiDone ? " ✓" : rfiReady ? " — ready" : " — after build readiness"}
          </li>
        </ul>

        <div className="mt-4 flex flex-wrap gap-2">
          {!p6Done ? (
            <Link
              href={`/project-one/rollouts/${rolloutId}?phase=skom`}
              className={cn(buttonVariants({ size: "sm", variant: "outline" }))}
            >
              Open SKOM
            </Link>
          ) : null}
          {rfiReady && !rfiDone ? (
            <p className="self-center text-xs text-muted-foreground">
              Use the <span className="font-medium text-foreground">Record RFI</span> card above when the
              site is ready.
            </p>
          ) : null}
        </div>
      </div>
    </div>
  );
}
