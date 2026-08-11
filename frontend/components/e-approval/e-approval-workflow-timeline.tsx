"use client";

import { CheckCircle2, Circle, Clock } from "lucide-react";

import { EApprovalSignaturePreview } from "@/components/e-approval/e-approval-signature-preview";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import type { EApprovalApprovalRow } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

function formatTimestamp(value: string | null | undefined): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function stepIcon(status: string) {
  const normalized = status.toLowerCase();
  if (normalized === "approved") return CheckCircle2;
  if (normalized === "pending") return Clock;
  return Circle;
}

type Props = {
  approvals: EApprovalApprovalRow[];
};

export function EApprovalWorkflowTimeline({ approvals }: Props) {
  if (approvals.length === 0) {
    return <p className="text-sm text-muted-foreground">No approval steps recorded.</p>;
  }

  return (
    <ol className="relative space-y-0">
      {approvals.map((approval, index) => {
        const Icon = stepIcon(approval.status);
        const isLast = index === approvals.length - 1;

        return (
          <li key={approval.id} className="relative flex gap-4 pb-8 last:pb-0">
            {!isLast ? (
              <span
                className="absolute left-[15px] top-8 h-[calc(100%-1rem)] w-px bg-border"
                aria-hidden
              />
            ) : null}
            <div
              className={cn(
                "relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-border bg-card",
                approval.status === "approved" && "border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950/40 dark:text-green-400",
                approval.status === "pending" && "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-400",
              )}
            >
              <Icon className="h-4 w-4" aria-hidden />
            </div>
            <div className="min-w-0 flex-1 rounded-lg border border-border bg-muted/20 p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm font-medium text-foreground">
                    Step {approval.step_order ?? "—"}
                    {approval.approver?.name ? ` · ${approval.approver.name}` : ""}
                  </p>
                  {approval.approver?.email ? (
                    <p className="mt-0.5 text-xs text-muted-foreground">{approval.approver.email}</p>
                  ) : null}
                </div>
                <EApprovalStatusBadge status={approval.status} kind="approval" />
              </div>
              {approval.remarks ? (
                <p className="mt-2 text-sm text-muted-foreground">
                  <span className="font-medium text-foreground">Remarks: </span>
                  {approval.remarks}
                </p>
              ) : null}
              {approval.signature ? (
                <div className="mt-3 max-w-xs">
                  <EApprovalSignaturePreview value={approval.signature} label="Signature" />
                </div>
              ) : null}
              <p className="mt-2 text-xs text-muted-foreground">
                {approval.acted_at ? `Acted ${formatTimestamp(approval.acted_at)}` : "Awaiting action"}
              </p>
            </div>
          </li>
        );
      })}
    </ol>
  );
}
