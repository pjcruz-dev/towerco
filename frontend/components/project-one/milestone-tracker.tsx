"use client";

import { ProjectMilestoneLabel } from "@/components/help/milestone-phase-label";
import { MilestoneWorkflowActions } from "@/components/project-one/milestone-workflow-actions";
import type { ProjectOneMilestone } from "@/modules/project-one/types";

function statusLabel(status: ProjectOneMilestone["status"]) {
  if (status === "on_track") return "On Track";
  if (status === "at_risk") return "At Risk";
  return "Blocked";
}

function statusClass(status: ProjectOneMilestone["status"]) {
  if (status === "on_track") return "text-green-600 dark:text-green-400";
  if (status === "at_risk") return "text-amber-600 dark:text-amber-400";
  return "text-red-600 dark:text-red-400";
}

function workflowLabel(workflow: ProjectOneMilestone["workflowStatus"]) {
  return workflow.replace(/_/g, " ");
}

export function MilestoneTracker({ items }: { items: ProjectOneMilestone[] }) {
  return (
    <section className="rounded-xl border bg-card p-4 shadow-sm">
      <h2 className="text-lg font-semibold">Milestones</h2>
      <p className="text-xs text-muted-foreground">Delivery checkpoints and execution risk.</p>

      <div className="mt-4 space-y-3">
        {items.length === 0 ? (
          <p className="text-sm text-muted-foreground">No milestones available.</p>
        ) : (
          items.map((item) => {
            const workflow = item.workflowStatus ?? "pending";

            return (
              <article key={item.id} className="rounded-lg border p-3.5 sm:p-3">
                <header className="mb-2 flex flex-wrap items-center justify-between gap-2">
                  <p className="text-sm font-medium">
                    <ProjectMilestoneLabel name={item.name} />
                  </p>
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded bg-muted px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                      {workflowLabel(workflow)}
                    </span>
                    <span className={`text-xs font-medium ${statusClass(item.status)}`}>
                      {statusLabel(item.status)}
                    </span>
                  </div>
                </header>
                <div className="h-2 w-full rounded bg-muted">
                  <div
                    className="h-2 rounded bg-primary transition-[width]"
                    style={{ width: `${Math.max(0, Math.min(100, item.progressPercent))}%` }}
                  />
                </div>
                <footer className="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                  <span>{item.progressPercent}% complete</span>
                  <span>Target: {item.targetDate || "—"}</span>
                </footer>
                <div className="mt-3">
                  <div className="[&_button]:min-h-11 [&_button]:touch-manipulation sm:[&_button]:min-h-9">
                    <MilestoneWorkflowActions
                      milestoneId={item.id}
                      status={workflow}
                      invalidateKeys={[["project-one", "dashboard"]]}
                    />
                  </div>
                </div>
              </article>
            );
          })
        )}
      </div>
    </section>
  );
}
