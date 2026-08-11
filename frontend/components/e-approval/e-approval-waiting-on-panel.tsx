"use client";

import { Clock3 } from "lucide-react";

import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import {
  describeParallelWaitingRule,
  getCurrentPendingApprovals,
} from "@/modules/e-approval/status-display";
import type { EApprovalApprovalRow } from "@/modules/e-approval/types";

type Props = {
  approvals: EApprovalApprovalRow[];
  currentStep?: number | null;
  submissionStatus: string;
  viewerPendingApprovalId?: string | null;
};

export function EApprovalWaitingOnPanel({
  approvals,
  currentStep,
  submissionStatus,
  viewerPendingApprovalId,
}: Props) {
  const status = submissionStatus.trim().toLowerCase();
  if (status !== "pending" && status !== "awaiting_dcf") {
    return null;
  }

  const waiting = getCurrentPendingApprovals(
    approvals,
    currentStep == null ? undefined : currentStep,
  );

  if (waiting.length === 0) {
    if (status === "awaiting_dcf") {
      return (
        <div
          className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
          role="status"
        >
          <div className="flex items-start gap-2">
            <Clock3 className="mt-0.5 h-4 w-4 shrink-0" />
            <div>
              <p className="font-medium">Waiting on document control</p>
              <p className="mt-1 text-xs text-amber-900/90 dark:text-amber-100/90">
                Approval steps are paused until document control finishes.
              </p>
            </div>
          </div>
        </div>
      );
    }

    return null;
  }

  const stepOrder = waiting[0]?.step_order;
  const rule = describeParallelWaitingRule(waiting);

  return (
    <div
      className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
      role="status"
    >
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="flex items-start gap-2">
          <Clock3 className="mt-0.5 h-4 w-4 shrink-0" />
          <div>
            <p className="font-medium">
              Waiting on{stepOrder != null ? ` Step ${stepOrder}` : ""}
              {waiting.length > 1 ? ` · ${waiting.length} approvers` : ""}
            </p>
            <p className="mt-1 text-xs text-amber-900/90 dark:text-amber-100/90">
              {rule ??
                (waiting.length === 1
                  ? "This approver must act before the workflow continues."
                  : "These approvers are active for the current step.")}
            </p>
          </div>
        </div>
        <EApprovalStatusBadge status="pending" kind="approval" />
      </div>

      <ul className="mt-3 space-y-2">
        {waiting.map((approval) => {
          const isYou = viewerPendingApprovalId != null && approval.id === viewerPendingApprovalId;
          const name = approval.approver?.name?.trim() || "Approver";
          const email = approval.approver?.email?.trim();

          return (
            <li
              key={approval.id}
              className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200/80 bg-card/70 px-3 py-2 dark:border-amber-900/40"
            >
              <div className="min-w-0">
                <p className="font-medium text-foreground">
                  {name}
                  {isYou ? (
                    <span className="ml-2 text-xs font-medium text-sky-700 dark:text-sky-300">You</span>
                  ) : null}
                </p>
                {email ? <p className="truncate text-xs text-muted-foreground">{email}</p> : null}
              </div>
              <span className="text-xs text-muted-foreground">Awaiting action</span>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
