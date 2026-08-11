"use client";

import type { ReactNode } from "react";

import { MilestonePhaseLabel } from "@/components/help/milestone-phase-label";

type Props = {
  phaseKey: string;
  phaseLabel: string;
  summary?: string | null;
  children: ReactNode;
  headerActions?: ReactNode;
};

/** Visual container for phase child forms embedded under the timeline. */
export function RolloutPhaseWorkShell({ phaseKey, phaseLabel, summary, children, headerActions }: Props) {
  return (
    <section
      className="rounded-lg border border-border border-l-4 border-l-primary bg-card shadow-sm"
      aria-labelledby={`phase-work-title-${phaseKey}`}
    >
      <header className="flex flex-wrap items-start justify-between gap-2 border-b border-border px-4 py-3 sm:px-5">
        <div>
          <h3 id={`phase-work-title-${phaseKey}`} className="text-base font-medium text-foreground">
            <MilestonePhaseLabel phaseKey={phaseKey} label={phaseLabel} />
          </h3>
          {summary ? <p className="mt-0.5 text-xs text-muted-foreground">{summary}</p> : null}
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {headerActions}
          <p className="text-xs text-muted-foreground">Phase work</p>
        </div>
      </header>
      <div className="px-4 py-4 sm:px-5">{children}</div>
    </section>
  );
}
