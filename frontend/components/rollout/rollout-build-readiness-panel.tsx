"use client";

import Link from "next/link";

import { buttonVariants } from "@/components/ui/button";
import {
  isBuildReadinessComplete,
  isDayOneSet,
  isPermittingPassed,
  isPermittingReady,
  isPreConstructionPassed,
  isPreConstructionReady,
  isSkomPassed,
  isSkomReady,
} from "@/lib/rollout/phase-gate-readiness";
import type { RolloutDetail } from "@/modules/rollout/types";
import { cn } from "@/lib/utils";

type Props = {
  rolloutId: string;
  detail: RolloutDetail;
  phaseKey: string;
};

function stepSuffix(passed: boolean, ready: boolean, isCurrent: boolean): string {
  if (passed) {
    return " ✓";
  }
  if (isCurrent) {
    return ready ? " — ready" : " — blocked";
  }
  return ready ? " — ready next" : " — pending";
}

/**
 * P6 — Build readiness checklist (Pre-con → Permitting → SKOM).
 */
export function RolloutBuildReadinessPanel({ rolloutId, detail, phaseKey }: Props) {
  const dayOne = isDayOneSet(detail);
  const preConPassed = isPreConstructionPassed(detail);
  const permittingPassed = isPermittingPassed(detail);
  const skomPassed = isSkomPassed(detail);
  const complete = isBuildReadinessComplete(detail);

  const title =
    phaseKey === "permitting"
      ? "Permitting"
      : phaseKey === "skom"
        ? "SKOM / Mobilization"
        : "Pre-Construction";

  return (
    <div className="space-y-3">
      {!dayOne ? (
        <div className="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
          Complete <span className="font-medium">Day-1</span> (record TSSR approved date) before build readiness.
        </div>
      ) : null}

      {complete ? (
        <div className="rounded-lg border border-green-200 bg-green-50/80 px-3 py-2 text-sm text-green-950 dark:border-green-900 dark:bg-green-950/30 dark:text-green-100">
          Build readiness complete — proceed to <span className="font-medium">Construction</span>.
        </div>
      ) : null}

      <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
        <h3 className="text-sm font-medium text-foreground">{title}</h3>
        <p className="mt-1 text-sm text-muted-foreground">
          Post–Day-1 sequence: Pre-Construction → Permitting → SKOM. Gates must pass in order before Construction.
        </p>
        <ul className="mt-3 list-inside list-disc text-xs text-muted-foreground">
          <li>Day-1 recorded{dayOne ? " ✓" : ""}</li>
          <li>
            Pre-Construction gate
            {stepSuffix(preConPassed, isPreConstructionReady(detail), phaseKey === "pre_construction")}
          </li>
          <li>
            Permitting gate
            {stepSuffix(permittingPassed, isPermittingReady(detail), phaseKey === "permitting")}
          </li>
          <li>
            SKOM / Mobilization gate
            {stepSuffix(skomPassed, isSkomReady(detail), phaseKey === "skom")}
          </li>
        </ul>

        <div className="mt-4 flex flex-wrap gap-2">
          {!dayOne ? (
            <Link
              href={`/project-one/rollouts/${rolloutId}?phase=tssr_mno_approval`}
              className={cn(buttonVariants({ size: "sm", variant: "outline" }))}
            >
              Open Day-1
            </Link>
          ) : null}
          {dayOne && !preConPassed ? (
            <Link
              href={`/project-one/rollouts/${rolloutId}?phase=pre_construction`}
              className={cn(buttonVariants({ size: "sm", variant: "outline" }))}
            >
              Pre-Construction
            </Link>
          ) : null}
          {preConPassed && !permittingPassed ? (
            <Link
              href={`/project-one/rollouts/${rolloutId}?phase=permitting`}
              className={cn(buttonVariants({ size: "sm", variant: "outline" }))}
            >
              Permitting
            </Link>
          ) : null}
          {permittingPassed && !skomPassed ? (
            <Link
              href={`/project-one/rollouts/${rolloutId}?phase=skom`}
              className={cn(buttonVariants({ size: "sm", variant: "outline" }))}
            >
              SKOM
            </Link>
          ) : null}
          <p className="self-center text-xs text-muted-foreground">
            Use <span className="font-medium text-foreground">Request</span> on the gate column when ready.
          </p>
        </div>
      </div>
    </div>
  );
}
