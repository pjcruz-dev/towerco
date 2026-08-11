import type { EApprovalApprovalRow } from "@/modules/e-approval/types";

export type EApprovalStatusKind = "form" | "submission" | "approval";

export type EApprovalStatusTone = "neutral" | "success" | "warning" | "danger" | "info";

const toneClass: Record<EApprovalStatusTone, string> = {
  neutral: "border-border bg-muted/50 text-muted-foreground",
  success: "border-green-600/20 bg-green-600/10 text-green-700 dark:text-green-400",
  warning: "border-amber-600/20 bg-amber-600/10 text-amber-800 dark:text-amber-400",
  danger: "border-destructive/30 bg-destructive/10 text-destructive",
  // Brand blue — not `--primary` (Geist near-black would make draft look charcoal)
  info: "border-sky-600/20 bg-sky-600/10 text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300",
}

const labelOverrides: Record<string, string> = {
  awaiting_dcf: "Awaiting document control",
  awaiting_me: "Awaiting me",
  invalidated: "Not needed",
  returned: "Needs revision",
  superseded: "Prior cycle",
};

export function formatEApprovalStatusLabel(status: string): string {
  const key = status.trim().toLowerCase();
  if (labelOverrides[key]) {
    return labelOverrides[key];
  }

  if (key === "all") {
    return "All";
  }

  return key
    .split(/[_\s-]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

export function resolveEApprovalStatusTone(status: string, kind: EApprovalStatusKind): EApprovalStatusTone {
  const key = status.trim().toLowerCase();

  if (kind === "form") {
    if (key === "published") return "success";
    return "neutral";
  }

  if (kind === "approval") {
    if (key === "approved") return "success";
    if (key === "rejected") return "danger";
    if (key === "pending") return "warning";
    if (
      key === "skipped" ||
      key === "invalidated" ||
      key === "returned" ||
      key === "superseded" ||
      key === "cancelled"
    ) {
      return "neutral";
    }
    return "neutral";
  }

  switch (key) {
    case "draft":
      return "info";
    case "approved":
      return "success";
    case "pending":
    case "returned":
    case "awaiting_dcf":
      return "warning";
    case "rejected":
    case "cancelled":
      return key === "rejected" ? "danger" : "neutral";
    default:
      return "neutral";
  }
}

export function eApprovalStatusBadgeClass(status: string, kind: EApprovalStatusKind): string {
  return toneClass[resolveEApprovalStatusTone(status, kind)];
}

/** Raw workflow-step status (not the inbox display status that mirrors submission state). */
export function getEApprovalApprovalStepStatus(
  approval: Pick<EApprovalApprovalRow, "status" | "approval_status">,
): string {
  return (approval.approval_status ?? approval.status).trim().toLowerCase();
}

/** The approval row that can still be decided, preferring the submission's current step. */
export function getCurrentPendingApproval(
  approvals: EApprovalApprovalRow[] | undefined,
  currentStep?: number,
): EApprovalApprovalRow | undefined {
  return getCurrentPendingApprovals(approvals, currentStep)[0];
}

/** All pending approvals at the current step (parallel band) or earliest pending order. */
export function getCurrentPendingApprovals(
  approvals: EApprovalApprovalRow[] | undefined,
  currentStep?: number,
): EApprovalApprovalRow[] {
  if (!approvals?.length) {
    return [];
  }

  const pending = approvals.filter((row) => getEApprovalApprovalStepStatus(row) === "pending");
  if (pending.length === 0) {
    return [];
  }

  if (currentStep != null) {
    const onCurrentStep = pending.filter((row) => row.step_order === currentStep);
    if (onCurrentStep.length > 0) {
      return onCurrentStep;
    }
  }

  const earliest = Math.min(...pending.map((row) => row.step_order ?? Number.MAX_SAFE_INTEGER));
  return pending.filter((row) => (row.step_order ?? earliest) === earliest);
}

export function describeParallelWaitingRule(
  pending: EApprovalApprovalRow[],
): string | null {
  if (pending.length < 2) {
    return null;
  }

  const mode = pending.find((row) => row.parallel_mode)?.parallel_mode ?? "all";
  const quorum = pending.find((row) => row.parallel_quorum != null)?.parallel_quorum ?? 1;

  if (mode === "any") {
    return "Any one can approve — first approval continues the workflow.";
  }
  if (mode === "n_of_m") {
    return `At least ${quorum} of ${pending.length} must approve before the workflow continues.`;
  }

  return "All listed approvers must approve before the workflow continues.";
}

/** True when the row belongs to a superseded / older approval cycle (audit history). */
export function isApprovalTrailPriorCycle(row: EApprovalApprovalRow): boolean {
  if (row.is_prior_cycle === true) {
    return true;
  }

  return getEApprovalApprovalStepStatus(row) === "superseded";
}

/**
 * Split trail into current-cycle activity vs prior-cycle history.
 * Current includes parallel "Not needed" (invalidated) peers on the active path.
 */
export function splitApprovalTrailCycles(approvals: EApprovalApprovalRow[]): {
  current: EApprovalApprovalRow[];
  prior: EApprovalApprovalRow[];
} {
  const sorted = sortApprovalTrailRows(approvals);
  const current: EApprovalApprovalRow[] = [];
  const prior: EApprovalApprovalRow[] = [];

  for (const row of sorted) {
    if (isApprovalTrailPriorCycle(row)) {
      prior.push(row);
    } else {
      current.push(row);
    }
  }

  return { current, prior };
}

/** System batch remark written on full workflow restart — show lightly in prior history. */
export function isSystemSupersedeRemark(remarks: string | null | undefined): boolean {
  const text = remarks?.trim().toLowerCase() ?? "";
  return text.includes("superseded by full workflow restart");
}

export type ApprovalTrailHistoryScope = "current" | "all";

/** Print / PDF always includes prior cycles; interactive views honor the filter. */
export function resolveApprovalTrailHistoryScope(
  historyScope: ApprovalTrailHistoryScope,
  options?: { alwaysShowFullHistory?: boolean },
): ApprovalTrailHistoryScope {
  if (options?.alwaysShowFullHistory) {
    return "all";
  }
  return historyScope;
}

/** Sort trail: current cycle first, then by step; within a step acted rows then pending. */
export function sortApprovalTrailRows(approvals: EApprovalApprovalRow[]): EApprovalApprovalRow[] {
  const statusRank = (row: EApprovalApprovalRow): number => {
    const status = getEApprovalApprovalStepStatus(row);
    if (status === "approved") return 0;
    if (status === "rejected") return 1;
    if (status === "pending") return 2;
    return 3;
  };

  return [...approvals].sort((left, right) => {
    const leftPrior = left.is_prior_cycle ? 1 : 0;
    const rightPrior = right.is_prior_cycle ? 1 : 0;
    if (leftPrior !== rightPrior) {
      return leftPrior - rightPrior;
    }

    const leftStep = left.step_order ?? Number.MAX_SAFE_INTEGER;
    const rightStep = right.step_order ?? Number.MAX_SAFE_INTEGER;
    if (leftStep !== rightStep) {
      return leftStep - rightStep;
    }

    const byStatus = statusRank(left) - statusRank(right);
    if (byStatus !== 0) {
      return byStatus;
    }

    const leftActed = left.acted_at ? Date.parse(left.acted_at) : Number.MAX_SAFE_INTEGER;
    const rightActed = right.acted_at ? Date.parse(right.acted_at) : Number.MAX_SAFE_INTEGER;
    if (leftActed !== rightActed) {
      return leftActed - rightActed;
    }

    return left.id.localeCompare(right.id);
  });
}
