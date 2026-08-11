"use client";

import type { FormComposeStep } from "@/modules/e-approval/form-compose-steps";
import { cn } from "@/lib/utils";

type Props = {
  steps: FormComposeStep[];
  currentStep: number;
  /** Step indices that passed validation (green). Visit alone is not enough. */
  completedSteps?: ReadonlySet<number>;
  onStepSelect?: (stepIndex: number) => void;
  /** Enables step tab buttons (requestor: backward only unless allowAnyStep). */
  allowStepSelect?: boolean;
  /** Design canvas: every step tab is clickable. Requestor flow: only current and prior steps. */
  allowAnyStep?: boolean;
  className?: string;
};

export function EApprovalComposeStepNav({
  steps,
  currentStep,
  completedSteps,
  onStepSelect,
  allowStepSelect = false,
  allowAnyStep = false,
  className,
}: Props) {
  if (steps.length < 2) {
    return null;
  }

  return (
    <nav className={cn("rounded-xl border border-border bg-card p-3 shadow-sm", className)} aria-label="Form steps">
      <div className="mb-2 flex items-center justify-between gap-2">
        <p className="text-xs font-medium text-foreground">Steps</p>
        <p className="text-xs text-muted-foreground tabular-nums">
          {currentStep + 1} of {steps.length}
        </p>
      </div>
      <ol className="flex flex-wrap gap-1.5">
        {steps.map((step, index) => {
          const active = index === currentStep;
          const done = completedSteps?.has(index) === true && !active;
          const clickable = allowStepSelect && (allowAnyStep || done || active || index < currentStep);

          return (
            <li key={step.id}>
              {clickable && onStepSelect ? (
                <button
                  type="button"
                  className={cn(
                    "rounded-md border px-2.5 py-1 text-left text-[11px] transition-colors",
                    active
                      ? "border-primary/40 bg-primary/5 text-primary"
                      : done
                        ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300"
                        : "border-border bg-background text-muted-foreground hover:border-primary/30 hover:bg-muted/40 hover:text-foreground",
                  )}
                  onClick={() => onStepSelect(index)}
                >
                  <span className="font-medium">{step.label}</span>
                </button>
              ) : (
                <span
                  className={cn(
                    "inline-block rounded-md border px-2.5 py-1 text-[11px]",
                    active
                      ? "border-primary/40 bg-primary/5 text-primary"
                      : done
                        ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300"
                        : "border-border bg-muted/20 text-muted-foreground",
                  )}
                >
                  <span className="font-medium">{step.label}</span>
                </span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
