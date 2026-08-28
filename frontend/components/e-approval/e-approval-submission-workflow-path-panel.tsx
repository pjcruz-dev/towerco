"use client";

import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { GitBranch, Loader2 } from "lucide-react";

import { EApprovalWorkflowPathDiagram } from "@/components/e-approval/e-approval-workflow-path-diagram";
import {
  previewEApprovalSubmissionWorkflow,
  type EApprovalWorkflowPreviewResponse,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";

type Props = {
  submissionId: string;
  /** Parent submission status — used to keep path badges aligned after cancel. */
  submissionStatus?: string | null;
  enabled?: boolean;
  /** Shown under the path header when the last resubmit applied resume/restart routing. */
  revisionRoutingNote?: string | null;
};

function previewForSubmissionStatus(
  preview: EApprovalWorkflowPreviewResponse,
  submissionStatus: string | null | undefined,
): EApprovalWorkflowPreviewResponse {
  if ((submissionStatus ?? "").trim().toLowerCase() !== "cancelled") {
    return preview;
  }

  const keep = new Set(["approved", "rejected", "skipped", "cancelled"]);

  return {
    ...preview,
    resolved_steps: preview.resolved_steps.map((step) => {
      const runtime = (step.runtime_status ?? "").trim().toLowerCase();
      if (runtime && keep.has(runtime)) {
        return step;
      }

      return { ...step, runtime_status: "cancelled" };
    }),
  };
}

export function EApprovalSubmissionWorkflowPathPanel({
  submissionId,
  submissionStatus = null,
  enabled = true,
  revisionRoutingNote = null,
}: Props) {
  const query = useQuery({
    queryKey: ["e-approval", "submission", submissionId, "workflow-preview", submissionStatus ?? ""],
    queryFn: () => previewEApprovalSubmissionWorkflow(submissionId),
    enabled: enabled && Boolean(submissionId),
    staleTime: 0,
  });

  const preview = useMemo(
    () => (query.data ? previewForSubmissionStatus(query.data, submissionStatus) : null),
    [query.data, submissionStatus],
  );

  if (!enabled) {
    return null;
  }

  return (
    <div data-help="ea-detail-workflow-path" className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="flex items-start gap-2">
          <GitBranch className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
          <div>
            <h2 className="text-base font-medium">Workflow path</h2>
            <p className="mt-1 text-xs text-muted-foreground">
              Who runs for this request, what is parallel, and where the approval stands. Hover an
              approver name to view their signature.
            </p>
            {revisionRoutingNote ? (
              <p className="mt-1.5 text-xs text-muted-foreground">{revisionRoutingNote}</p>
            ) : null}
          </div>
        </div>
      </div>

      {query.isLoading ? (
        <p className="mt-4 flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          Loading path…
        </p>
      ) : null}

      {query.isError ? (
        <p className="mt-4 text-sm text-destructive">{getErrorMessage(query.error)}</p>
      ) : null}

      {preview ? (
        <div className="mt-4 space-y-3">
          {preview.matched_rule_label ? (
            <p className="text-xs text-muted-foreground">{preview.matched_rule_label}</p>
          ) : null}
          <EApprovalWorkflowPathDiagram preview={preview} compactDetails />
        </div>
      ) : null}
    </div>
  );
}
