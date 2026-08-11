"use client";

import Link from "next/link";

import { buttonVariants } from "@/components/ui/button";
import {
  hasSelectedSaqCandidate,
  isPreAssessmentPassed,
  isPreAssessmentReady,
  isSiteHuntingGatePassed,
  selectedSaqCandidate,
} from "@/lib/rollout/phase-gate-readiness";
import type { RolloutDetail } from "@/modules/rollout/types";
import { cn } from "@/lib/utils";

type Props = {
  rolloutId: string;
  detail: RolloutDetail;
};

/**
 * P3 — Pre-assessment Approval (MNO) work panel.
 * Selected SAQ candidate is reviewed before TSSR create/review.
 */
export function RolloutPreAssessmentPanel({ rolloutId, detail }: Props) {
  const selected = selectedSaqCandidate(detail);
  const phase = detail.timeline_phases?.find((p) => p.phase_key === "pre_assessment");
  const passed = isPreAssessmentPassed(detail);
  const ready = isPreAssessmentReady(detail);
  const huntingPassed = isSiteHuntingGatePassed(detail);

  return (
    <div className="space-y-4">
      {!huntingPassed ? (
        <div className="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
          Complete <span className="font-medium">Site Hunting</span> (select a candidate and pass the gate)
          before MNO Pre-assessment.
        </div>
      ) : null}

      {huntingPassed && !hasSelectedSaqCandidate(detail) ? (
        <div className="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
          Select a candidate under Site Hunting before requesting Pre-assessment.
        </div>
      ) : null}

      {passed ? (
        <div className="rounded-lg border border-green-200 bg-green-50/80 px-3 py-2 text-sm text-green-950 dark:border-green-900 dark:bg-green-950/30 dark:text-green-100">
          Pre-assessment complete — selected candidate may proceed to <span className="font-medium">TSSR create/review</span>.
        </div>
      ) : null}

      {!passed ? (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h3 className="text-sm font-medium text-foreground">Pre-assessment Approval (MNO)</h3>
          <p className="mt-1 text-sm text-muted-foreground">
            MNO confirms the selected site candidate before engineering starts TSSR. Gate chain: MNO → PMO.
          </p>
          <ul className="mt-3 list-inside list-disc text-xs text-muted-foreground">
            <li>Site Hunting passed {huntingPassed ? "✓" : ""}</li>
            <li>
              Selected candidate{" "}
              {selected
                ? `✓ — ${selected.label ?? `#${selected.candidate_number}`}`
                : "— required"}
            </li>
            <li>Request / pass Pre-assessment gate on the timeline row</li>
          </ul>

          {selected ? (
            <div className="mt-4 rounded-lg border border-border/70 bg-muted/20 px-3 py-2 text-sm">
              <p className="font-medium text-foreground">
                {selected.label ?? `Candidate #${selected.candidate_number}`}
              </p>
              <p className="mt-0.5 text-xs text-muted-foreground">
                Status: {selected.status}
                {selected.lessor_name ? ` · Lessor ${selected.lessor_name}` : ""}
                {detail.tco_site_id ? ` · TCO ${detail.tco_site_id}` : ""}
              </p>
            </div>
          ) : null}

          <div className="mt-4 flex flex-wrap gap-2">
            <Link
              href={`/project-one/rollouts/${rolloutId}?phase=site_hunting`}
              className={cn(buttonVariants({ size: "sm", variant: "outline" }))}
            >
              Open SAQ
            </Link>
            {ready && phase ? (
              <p className="self-center text-xs text-muted-foreground">
                Use <span className="font-medium text-foreground">Request</span> on the Pre-assessment gate
                column above.
              </p>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}
