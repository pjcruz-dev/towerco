"use client";

import { ChevronDown, ChevronRight } from "lucide-react";
import { useMemo, useState } from "react";

import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import { EApprovalSignaturePreview } from "@/components/e-approval/e-approval-signature-preview";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { EApprovalApprovalRow } from "@/modules/e-approval/types";
import {
  getEApprovalApprovalStepStatus,
  isSystemSupersedeRemark,
  resolveApprovalTrailHistoryScope,
  splitApprovalTrailCycles,
  type ApprovalTrailHistoryScope,
} from "@/modules/e-approval/status-display";
import { hasSignatureValue } from "@/modules/e-approval/signature";

export type { ApprovalTrailHistoryScope };

type Props = {
  approvals: EApprovalApprovalRow[];
  currentStep: number | null | undefined;
  /** Parent submission status — cancelled/approved/rejected must not look like they are still waiting. */
  submissionStatus?: string | null;
  revisionRoutingNote?: string | null;
  /** When true, signature previews stay inline and full prior history is always shown (print / PDF). */
  alwaysShowSignatures?: boolean;
  defaultOpen?: boolean;
  /** Interactive default for history filter. Ignored when `alwaysShowSignatures` (print). */
  defaultHistoryScope?: ApprovalTrailHistoryScope;
  className?: string;
};

function formatTimestamp(value: string): string {
  try {
    return new Date(value).toLocaleString();
  } catch {
    return value;
  }
}

function TrailRow({
  approval,
  currentStep,
  submissionStatus,
  alwaysShowSignatures,
  compact,
}: {
  approval: EApprovalApprovalRow;
  currentStep: number | null | undefined;
  submissionStatus?: string | null;
  alwaysShowSignatures?: boolean;
  /** Lighter presentation for prior-cycle / system-supersede rows. */
  compact?: boolean;
}) {
  const parentStatus = (submissionStatus ?? "").trim().toLowerCase();
  const rawStepStatus = getEApprovalApprovalStepStatus(approval);
  const stepStatus =
    parentStatus === "cancelled" &&
    (rawStepStatus === "pending" || rawStepStatus === "invalidated" || rawStepStatus === "returned")
      ? "cancelled"
      : rawStepStatus;
  const isWaiting =
    stepStatus === "pending" &&
    parentStatus !== "cancelled" &&
    parentStatus !== "approved" &&
    parentStatus !== "rejected" &&
    !approval.is_prior_cycle &&
    (currentStep == null || approval.step_order === currentStep);
  const name = approval.approver?.name?.trim() || "Approver";
  const systemSupersede = isSystemSupersedeRemark(approval.remarks);
  const showRemarks = Boolean(approval.remarks?.trim()) && !(compact && systemSupersede);

  return (
    <li
      className={cn(
        "rounded-lg border p-3 text-sm",
        isWaiting
          ? "border-amber-200 bg-amber-50/50 dark:border-amber-900/50 dark:bg-amber-950/20"
          : compact
            ? "border-border/70 bg-muted/20 text-muted-foreground"
            : "border-border bg-muted/30",
      )}
    >
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className={cn("font-medium", compact && "font-normal text-foreground/80")}>
          Step {approval.step_order ?? "—"} · {name}
          {compact ? (
            <span className="ml-2 text-xs font-normal text-muted-foreground">(prior cycle)</span>
          ) : null}
        </span>
        <EApprovalStatusBadge status={compact && systemSupersede ? "superseded" : stepStatus} kind="approval" />
      </div>
      {approval.approver?.email ? (
        <p className="mt-1 text-muted-foreground">{approval.approver.email}</p>
      ) : null}
      {showRemarks ? (
        <p className="mt-2">
          <span className="text-muted-foreground">Remarks: </span>
          {approval.remarks}
        </p>
      ) : null}
      {compact && systemSupersede ? (
        <p className="mt-1 text-[11px] text-muted-foreground">Superseded by full workflow restart</p>
      ) : null}
      {alwaysShowSignatures && hasSignatureValue(approval.signature) ? (
        <div className="mt-3 max-w-xs">
          <EApprovalSignaturePreview value={approval.signature} label="Signature" />
        </div>
      ) : null}
      <p className="mt-1 text-xs text-muted-foreground">
        {approval.acted_at
          ? `Acted ${formatTimestamp(approval.acted_at)}`
          : compact
            ? "No action in this cycle"
            : stepStatus === "cancelled"
              ? "Cancelled — no action taken"
              : "Awaiting action"}
      </p>
    </li>
  );
}

export function EApprovalApprovalTrail({
  approvals,
  currentStep,
  submissionStatus = null,
  revisionRoutingNote,
  alwaysShowSignatures = false,
  defaultOpen = false,
  defaultHistoryScope = "all",
  className,
}: Props) {
  const [open, setOpen] = useState(defaultOpen);
  const [historyScope, setHistoryScope] = useState<ApprovalTrailHistoryScope>(defaultHistoryScope);
  // Print / PDF expands prior history; interactive view keeps it collapsed until opened.
  const [priorOpen, setPriorOpen] = useState(alwaysShowSignatures);

  const { current, prior } = useMemo(() => splitApprovalTrailCycles(approvals), [approvals]);
  // Print / PDF always includes full audit history regardless of the interactive filter.
  const effectiveScope = resolveApprovalTrailHistoryScope(historyScope, {
    alwaysShowFullHistory: alwaysShowSignatures,
  });
  const showPriorSection = prior.length > 0 && effectiveScope === "all";
  const visibleCount = current.length + (showPriorSection ? prior.length : 0);
  const showHistoryFilter = !alwaysShowSignatures && prior.length > 0;

  return (
    <div className={cn("rounded-xl border border-border bg-card p-4 shadow-sm", className)}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <button
          type="button"
          className="min-w-0 flex-1 text-left"
          onClick={() => setOpen((prev) => !prev)}
          aria-expanded={open}
        >
          <h2 className="flex items-center gap-1.5 text-base font-medium">
            {open ? (
              <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground" />
            ) : (
              <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
            )}
            Approval trail
          </h2>
          <p className="mt-1 text-xs text-muted-foreground">
            Remarks and timestamps for each approver
            {visibleCount > 0 ? ` · ${visibleCount} shown` : ""}
            {prior.length > 0 ? ` · ${prior.length} prior-cycle` : ""}.
            {alwaysShowSignatures
              ? " Full history included for print."
              : " Signatures are on the workflow path — hover an approver name there."}
          </p>
        </button>

        {showHistoryFilter ? (
          <div
            className="flex shrink-0 items-center gap-1 rounded-lg border border-border bg-muted/30 p-0.5"
            role="group"
            aria-label="Approval trail history filter"
            onClick={(e) => e.stopPropagation()}
          >
            <Button
              type="button"
              size="sm"
              variant={historyScope === "current" ? "secondary" : "ghost"}
              className="h-7 px-2.5 text-xs"
              onClick={() => setHistoryScope("current")}
              aria-pressed={historyScope === "current"}
            >
              Current only
            </Button>
            <Button
              type="button"
              size="sm"
              variant={historyScope === "all" ? "secondary" : "ghost"}
              className="h-7 px-2.5 text-xs"
              onClick={() => {
                setHistoryScope("all");
                setPriorOpen(true);
              }}
              aria-pressed={historyScope === "all"}
            >
              Include history
            </Button>
          </div>
        ) : null}
      </div>

      {revisionRoutingNote && (open || alwaysShowSignatures) ? (
        <p className={cn("mt-2 text-xs text-muted-foreground", !open && "hidden print:block")}>
          {revisionRoutingNote}
        </p>
      ) : null}

      {current.length + prior.length === 0 ? (
        open ? (
          <p className="mt-4 text-sm text-muted-foreground">No approval steps recorded.</p>
        ) : null
      ) : (
        <div className={cn("mt-4 space-y-4", !open && "hidden print:block")}>
          {current.length > 0 ? (
            <ul className="space-y-3">
              {current.map((approval) => (
                <TrailRow
                  key={approval.id}
                  approval={approval}
                  currentStep={currentStep}
                  submissionStatus={submissionStatus}
                  alwaysShowSignatures={alwaysShowSignatures}
                />
              ))}
            </ul>
          ) : (
            <p className="text-sm text-muted-foreground">No current-cycle approval activity yet.</p>
          )}

          {showPriorSection ? (
            <div className="border-t border-border pt-3">
              <button
                type="button"
                className="flex w-full items-center gap-1.5 text-left text-sm font-medium text-foreground print:pointer-events-none"
                onClick={() => setPriorOpen((prev) => !prev)}
                aria-expanded={priorOpen || alwaysShowSignatures}
              >
                {priorOpen || alwaysShowSignatures ? (
                  <ChevronDown className="h-3.5 w-3.5 shrink-0 text-muted-foreground print:hidden" />
                ) : (
                  <ChevronRight className="h-3.5 w-3.5 shrink-0 text-muted-foreground print:hidden" />
                )}
                Prior cycles · {prior.length} record{prior.length === 1 ? "" : "s"}
              </button>
              <p className="mt-1 text-[11px] text-muted-foreground print:hidden">
                Audit history from earlier submission cycles.
                {historyScope === "all" && !priorOpen
                  ? " Expand to review, or use Current only to hide."
                  : " Browser print always includes this history."}
              </p>
              <ul
                className={cn(
                  "mt-3 space-y-2",
                  !(priorOpen || alwaysShowSignatures) && "hidden print:block",
                )}
              >
                {prior.map((approval) => (
                  <TrailRow
                    key={approval.id}
                    approval={approval}
                    currentStep={currentStep}
                    submissionStatus={submissionStatus}
                    alwaysShowSignatures={alwaysShowSignatures || !priorOpen}
                    compact
                  />
                ))}
              </ul>
            </div>
          ) : null}

          {prior.length > 0 && effectiveScope === "current" ? (
            <>
              <p className="border-t border-border pt-3 text-xs text-muted-foreground print:hidden">
                {prior.length} prior-cycle record{prior.length === 1 ? "" : "s"} hidden. Switch to
                Include history to review audit trail.
              </p>
              <div className="hidden border-t border-border pt-3 print:block">
                <p className="text-sm font-medium text-foreground">
                  Prior cycles · {prior.length} record{prior.length === 1 ? "" : "s"}
                </p>
                <ul className="mt-3 space-y-2">
                  {prior.map((approval) => (
                    <TrailRow
                      key={`print-${approval.id}`}
                      approval={approval}
                      currentStep={currentStep}
                      submissionStatus={submissionStatus}
                      alwaysShowSignatures
                      compact
                    />
                  ))}
                </ul>
              </div>
            </>
          ) : null}
        </div>
      )}
    </div>
  );
}
