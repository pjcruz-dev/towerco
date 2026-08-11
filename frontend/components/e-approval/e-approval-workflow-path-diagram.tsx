"use client";

import {
  WorkflowDiagramBandBlock,
  WorkflowDiagramConnector,
  WorkflowDiagramShell,
  WorkflowDiagramTerminal,
} from "@/components/e-approval/e-approval-workflow-diagram-chrome";
import type { EApprovalWorkflowPreviewResponse } from "@/lib/api/modules/e-approval-api";
import { buildWorkflowPathDiagram } from "@/modules/e-approval/workflow-path-diagram";
import { cn } from "@/lib/utils";

type Props = {
  preview: Pick<EApprovalWorkflowPreviewResponse, "resolved_steps" | "skipped_steps" | "matched_rule_label">;
  className?: string;
  /** Hide repetitive “conditions matched” lines (submission participant view). */
  compactDetails?: boolean;
};

export function EApprovalWorkflowPathDiagram({ preview, className, compactDetails = false }: Props) {
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

        {runBands.map((band) => (
          <div key={band.id} className="flex w-full flex-col items-center">
            <WorkflowDiagramConnector />
            <WorkflowDiagramBandBlock band={band} />
          </div>
        ))}

        {runBands.length > 0 ? (
          <>
            <WorkflowDiagramConnector />
            <WorkflowDiagramTerminal label="End" />
          </>
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
