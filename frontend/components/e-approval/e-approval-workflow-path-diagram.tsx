"use client";

import {
  WorkflowDiagramBandBlock,
  WorkflowDiagramConnector,
  WorkflowDiagramShell,
  WorkflowDiagramTerminal,
} from "@/components/e-approval/e-approval-workflow-diagram-chrome";
import type { EApprovalWorkflowPreviewResponse } from "@/lib/api/modules/e-approval-api";
import { buildWorkflowPathDiagram, trainingPathBand } from "@/modules/e-approval/workflow-path-diagram";
import { cn } from "@/lib/utils";

type TrainingPath = {
  holding: boolean;
  invites: { id: string; name: string; email: string; status: string }[];
};

type Props = {
  preview: Pick<EApprovalWorkflowPreviewResponse, "resolved_steps" | "skipped_steps" | "matched_rule_label">;
  training?: TrainingPath | null;
  className?: string;
  /** Hide repetitive “conditions matched” lines (submission participant view). */
  compactDetails?: boolean;
};

const FINISHED_STEP = new Set(["approved", "acknowledged", "training_done"]);

function bandWasNotSent(band: { nodes: { status?: string | null }[] }): boolean {
  return band.nodes.length > 0 && band.nodes.every((node) => {
    const status = (node.status ?? "").trim().toLowerCase();
    return status === "not_sent" || status === "skipped";
  });
}

function bandIsFinished(band: { nodes: { status?: string | null }[] }): boolean {
  const statuses = band.nodes.map((node) => (node.status ?? "").trim().toLowerCase());
  if (statuses.some((status) => status === "pending" || status === "")) {
    return false;
  }

  return statuses.some((status) => FINISHED_STEP.has(status));
}

function bandLineTone(
  band: { nodes: { status?: string | null }[] },
  earlierFinished: boolean,
): "done" | "flow" | "idle" | "returned" {
  const statuses = band.nodes.map((node) => (node.status ?? "").trim().toLowerCase());
  if (statuses.some((status) => status === "returned" || status === "rejected")) {
    return "returned";
  }
  if (bandIsFinished(band)) {
    return "done";
  }
  if (earlierFinished && statuses.some((status) => status === "pending" || status === "")) {
    return "flow";
  }

  return "idle";
}

export function EApprovalWorkflowPathDiagram({ preview, training = null, className, compactDetails = false }: Props) {
  const { runBands, skippedBands } = buildWorkflowPathDiagram(
    compactDetails
      ? {
          ...preview,
          resolved_steps: preview.resolved_steps.map((step) => ({ ...step, path_reason: null })),
          skipped_steps: (preview.skipped_steps ?? []).map((step) => ({
            ...step,
            path_reason: step.path_reason ?? null,
          })),
        }
      : preview,
  );

  if (runBands.length === 0 && skippedBands.length === 0) {
    return (
      <p className="text-xs text-amber-700 dark:text-amber-300">
        No steps to diagram. Check conditions or add an always-on step.
      </p>
    );
  }

  const visibleBands = runBands.filter((band) => !bandWasNotSent(band));
  const lastOrder = visibleBands.reduce((max, band) => Math.max(max, band.stepOrder), 0);
  const bands = training?.holding
    ? [...visibleBands, trainingPathBand(training.invites, lastOrder)]
    : visibleBands;

  return (
    <WorkflowDiagramShell
      title="Path diagram"
      className={cn(className)}
      legend={
        <div className="flex flex-wrap gap-3 text-[11px] text-muted-foreground">
          <span className="inline-flex items-center gap-1.5">
            <span className="h-2 w-2 rounded-full bg-sky-500" /> Runs
          </span>
          <span className="inline-flex items-center gap-1.5">
            <span className="h-2 w-2 rounded-full border border-dashed border-muted-foreground" />{" "}
            Skipped
          </span>
        </div>
      }
    >
      <div role="img" aria-label="Workflow path diagram" className="flex w-full flex-col items-center">
        <WorkflowDiagramTerminal label="Start" />

        {bands.map((band, index) => {
          const earlierFinished = bands.slice(0, index).every(bandIsFinished);
          const tone = bandLineTone(band, earlierFinished);

          return (
            <div key={band.id} className="flex w-full flex-col items-center">
              {band.nodes.length > 1 ? null : <WorkflowDiagramConnector tone={tone} />}
              <WorkflowDiagramBandBlock band={band} lineTone={tone} />
            </div>
          );
        })}

        {bands.length > 0 ? (
          <>
            <WorkflowDiagramConnector tone={bands.every(bandIsFinished) ? "done" : "idle"} />
            <WorkflowDiagramTerminal label="End" />
          </>
        ) : null}

        {runBands.some(bandWasNotSent) ? (
          <div className="mt-5 w-full border-t border-dashed border-border pt-4">
            <p className="mb-2 text-center text-xs font-medium text-muted-foreground">Not sent</p>
            {runBands.filter(bandWasNotSent).map((band) => (
              <div key={band.id} className="flex w-full flex-col items-center">
                <WorkflowDiagramBandBlock band={band} lineTone="idle" />
              </div>
            ))}
          </div>
        ) : null}

        {skippedBands.length > 0 ? (
          <div className="mt-5 w-full border-t border-dashed border-border pt-4">
            <p className="mb-2 text-center text-xs font-medium text-muted-foreground">
              Skipped for these values
            </p>
            {skippedBands.map((band, index) => (
              <div key={band.id} className="flex w-full flex-col items-center">
                {index > 0 ? <WorkflowDiagramConnector muted /> : null}
                <WorkflowDiagramBandBlock band={band} />
              </div>
            ))}
          </div>
        ) : null}
      </div>
    </WorkflowDiagramShell>
  );
}
