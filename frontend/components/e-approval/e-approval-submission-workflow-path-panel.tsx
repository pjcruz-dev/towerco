"use client";

import { useQuery } from "@tanstack/react-query";
import { GitBranch, Loader2 } from "lucide-react";

import { EApprovalWorkflowPathDiagram } from "@/components/e-approval/e-approval-workflow-path-diagram";
import { previewEApprovalSubmissionWorkflow } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";

type Props = {
  submissionId: string;
  enabled?: boolean;
  /** Shown under the path header when the last resubmit applied resume/restart routing. */
  revisionRoutingNote?: string | null;
};

export function EApprovalSubmissionWorkflowPathPanel({
  submissionId,
  enabled = true,
  revisionRoutingNote = null,
}: Props) {
  const query = useQuery({
    queryKey: ["e-approval", "submission", submissionId, "workflow-preview"],
    queryFn: () => previewEApprovalSubmissionWorkflow(submissionId),
    enabled: enabled && Boolean(submissionId),
    staleTime: 30_000,
  });

  if (!enabled) {
    return null;
  }

  return (
    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
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

      {query.data ? (
        <div className="mt-4 space-y-3">
          {query.data.matched_rule_label ? (
            <p className="text-xs text-muted-foreground">{query.data.matched_rule_label}</p>
          ) : null}
          <EApprovalWorkflowPathDiagram preview={query.data} compactDetails />
        </div>
      ) : null}
    </div>
  );
}
