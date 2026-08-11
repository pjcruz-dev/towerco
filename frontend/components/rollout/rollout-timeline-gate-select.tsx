"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { ChevronRight, MoreVertical } from "lucide-react";
import { useEffect, useRef, useState, type ReactNode } from "react";

import { GateApprovalActingLabel } from "@/components/rollout/gate-approval-acting-label";
import {
  GateBinderReadinessBanner,
  gateBinderBlocksFinalApprove,
} from "@/components/rollout/gate-binder-readiness-banner";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import {
  decideRolloutGateApproval,
  submitRolloutGateApproval,
  updateRolloutPhaseGate,
} from "@/lib/api/modules/rollout-api";
import type { RolloutTimelinePhase } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

const manualGateOptions = [
  { value: "pending", label: "Pending" },
  { value: "failed", label: "Failed" },
  { value: "waived", label: "Waived" },
  { value: "passed", label: "Passed" },
] as const;

type ActionPanel = null | "request" | "review";

type PrimaryLink =
  | { kind: "request"; label: string }
  | { kind: "review"; label: string }
  | { kind: "none" };

export function RolloutTimelineGateSelect({
  rolloutId,
  phase,
  canManage,
}: {
  rolloutId: string;
  phase: RolloutTimelinePhase;
  canManage: boolean;
}) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const [notes, setNotes] = useState("");
  const [menuOpen, setMenuOpen] = useState(false);
  const [actionPanel, setActionPanel] = useState<ActionPanel>(null);
  const rootRef = useRef<HTMLDivElement>(null);

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts", "detail", rolloutId] });
    queryClient.invalidateQueries({ queryKey: ["project-one", "gate-approvals"] });
  };

  const closePanels = () => {
    setMenuOpen(false);
    setActionPanel(null);
    setNotes("");
  };

  const gateMutation = useMutation({
    mutationFn: (gateStatus: string) => updateRolloutPhaseGate(phase.id, gateStatus),
    onSuccess: () => {
      invalidate();
      closePanels();
      push({ level: "success", title: "Gate status updated" });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not update gate", message: getErrorMessage(error) }),
  });

  const submitMutation = useMutation({
    mutationFn: () => submitRolloutGateApproval(phase.id, notes.trim() ? { request_notes: notes.trim() } : undefined),
    onSuccess: () => {
      invalidate();
      closePanels();
      push({ level: "success", title: "Approval requested", message: "Approvers were notified by email." });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not submit approval", message: getErrorMessage(error) }),
  });

  const decideMutation = useMutation({
    mutationFn: (decision: "approve" | "reject") =>
      decideRolloutGateApproval(phase.active_gate_approval!.id, {
        decision,
        notes: notes.trim() || undefined,
      }),
    onSuccess: (_, decision) => {
      invalidate();
      closePanels();
      push({
        level: decision === "approve" ? "success" : "warning",
        title: decision === "approve" ? "Step approved" : "Approval rejected",
        message: decision === "reject" ? "Gate remains pending — resubmit when ready." : undefined,
      });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not decide approval", message: getErrorMessage(error) }),
  });

  useEffect(() => {
    if (!menuOpen && !actionPanel) return;

    function onPointerDown(event: MouseEvent) {
      if (rootRef.current && !rootRef.current.contains(event.target as Node)) {
        setMenuOpen(false);
        setActionPanel(null);
      }
    }

    document.addEventListener("mousedown", onPointerDown);
    return () => document.removeEventListener("mousedown", onPointerDown);
  }, [menuOpen, actionPanel]);

  if (!phase.gate_label) {
    return <GateStatusBadge status={phase.gate_status} />;
  }

  const active = phase.active_gate_approval;
  const requiresApproval = Boolean(phase.approval_required);
  const canAct = Boolean(active?.can_act);
  const isPassed = phase.gate_status === "passed";
  const isInReview = active?.status === "in_review";
  const wasRejected = !active && phase.latest_gate_approval?.status === "rejected";

  const primaryLink: PrimaryLink = (() => {
    if (!canManage || isPassed) return { kind: "none" };
    if (canAct && isInReview) return { kind: "review", label: "Review" };
    if (requiresApproval && !isInReview) return { kind: "request", label: "Request" };
    return { kind: "none" };
  })();

  const showOverflow =
    canManage && !isPassed && (requiresApproval ? !isInReview || canAct : true);

  const manualStatusLocked = requiresApproval && (isInReview || isPassed);

  const stepHint =
    isInReview && active
      ? `Step ${active.current_step + 1}/${active.approval_chain.length} · ${active.current_approver_role}`
      : wasRejected
        ? "Rejected — request again when ready"
        : null;

  const actingFor = canAct && isInReview ? active?.acting_for : null;

  const badge = <GateStatusBadge status={phase.gate_status} inReview={isInReview} />;

  if (!canManage) {
    return (
      <div className="min-w-[120px]">
        <div className="flex items-center gap-2">{badge}</div>
        {stepHint ? (
          <p
            className={`mt-0.5 text-[11px] leading-tight ${wasRejected ? "text-red-600 dark:text-red-400" : "text-muted-foreground"}`}
          >
            {stepHint}
          </p>
        ) : null}
      </div>
    );
  }

  const busy = submitMutation.isPending || decideMutation.isPending || gateMutation.isPending;

  return (
    <div className="relative min-w-[140px]" ref={rootRef}>
      <div className="flex items-center gap-1.5">
        {badge}

        {primaryLink.kind === "request" ? (
          <button
            type="button"
            className="inline-flex items-center gap-0.5 text-xs font-medium text-primary hover:underline disabled:opacity-50"
            disabled={busy}
            onClick={() => {
              setMenuOpen(false);
              setActionPanel((panel) => (panel === "request" ? null : "request"));
            }}
          >
            {primaryLink.label}
            <ChevronRight className="size-3" aria-hidden />
          </button>
        ) : null}

        {primaryLink.kind === "review" ? (
          <button
            type="button"
            className="inline-flex items-center gap-0.5 text-xs font-medium text-primary hover:underline disabled:opacity-50"
            disabled={busy}
            onClick={() => {
              setMenuOpen(false);
              setActionPanel((panel) => (panel === "review" ? null : "review"));
            }}
          >
            {primaryLink.label}
            <ChevronRight className="size-3" aria-hidden />
          </button>
        ) : null}

        {showOverflow ? (
          <button
            type="button"
            className="inline-flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
            aria-label="More gate actions"
            aria-expanded={menuOpen}
            disabled={busy}
            onClick={() => {
              setActionPanel(null);
              setMenuOpen((open) => !open);
            }}
          >
            <MoreVertical className="size-4" />
          </button>
        ) : null}
      </div>

      {stepHint ? (
        <p
          className={`mt-0.5 text-[11px] leading-tight ${wasRejected ? "text-red-600 dark:text-red-400" : "text-muted-foreground"}`}
        >
          {stepHint}
        </p>
      ) : null}
      <GateApprovalActingLabel actingFor={actingFor} className="mt-0.5" />

      {actionPanel === "request" ? (
        <GateActionPopover
          title="Request approval"
          notes={notes}
          onNotesChange={setNotes}
          notesPlaceholder="Optional notes for approvers"
          onCancel={() => {
            setActionPanel(null);
            setNotes("");
          }}
          actions={
            <Button
              type="button"
              size="sm"
              className="h-8 text-xs"
              disabled={submitMutation.isPending}
              onClick={() => submitMutation.mutate()}
            >
              {submitMutation.isPending ? "Submitting…" : "Submit request"}
            </Button>
          }
        />
      ) : null}

      {actionPanel === "review" && active ? (
        <GateActionPopover
          title="Review gate step"
          description={
            active.acting_for
              ? `Acting for ${active.acting_for.name} · step ${active.current_step + 1} of ${active.approval_chain.length}`
              : `Step ${active.current_step + 1} of ${active.approval_chain.length} · ${active.current_approver_role}`
          }
          notes={notes}
          onNotesChange={setNotes}
          notesPlaceholder="Decision notes (optional)"
          onCancel={() => {
            setActionPanel(null);
            setNotes("");
          }}
          banner={
            <GateBinderReadinessBanner
              gate={phase.document_binder_gate ?? active.document_binder_gate}
              blocksApprove={gateBinderBlocksFinalApprove(
                phase.document_binder_gate ?? active.document_binder_gate,
                active.is_final_step ??
                  active.current_step + 1 >= active.approval_chain.length,
              )}
            />
          }
          actions={
            <>
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-8 text-xs"
                disabled={decideMutation.isPending}
                onClick={() => decideMutation.mutate("reject")}
              >
                Reject
              </Button>
              <Button
                type="button"
                size="sm"
                className="h-8 text-xs"
                disabled={
                  decideMutation.isPending ||
                  gateBinderBlocksFinalApprove(
                    phase.document_binder_gate ?? active.document_binder_gate,
                    active.is_final_step ??
                      active.current_step + 1 >= active.approval_chain.length,
                  )
                }
                title={
                  gateBinderBlocksFinalApprove(
                    phase.document_binder_gate ?? active.document_binder_gate,
                    active.is_final_step ??
                      active.current_step + 1 >= active.approval_chain.length,
                  )
                    ? "Complete the site binder checklist before final approve"
                    : undefined
                }
                onClick={() => decideMutation.mutate("approve")}
              >
                {decideMutation.isPending
                  ? "Saving…"
                  : active.acting_for
                    ? `Approve for ${active.acting_for.name.split(" ")[0]}`
                    : "Approve"}
              </Button>
            </>
          }
        />
      ) : null}

      {menuOpen ? (
        <div
          className="absolute right-0 z-20 mt-1 w-44 rounded-lg border border-border bg-card py-1 shadow-md"
          role="menu"
        >
          {!manualStatusLocked
            ? manualGateOptions
                .filter((option) => option.value !== phase.gate_status)
                .map((option) => {
                  const blockPassed =
                    option.value === "passed" &&
                    gateBinderBlocksFinalApprove(phase.document_binder_gate, true);
                  return (
                    <button
                      key={option.value}
                      type="button"
                      role="menuitem"
                      className="flex w-full px-3 py-2 text-left text-sm text-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                      disabled={gateMutation.isPending || blockPassed}
                      title={
                        blockPassed
                          ? "Complete the site binder checklist before marking passed"
                          : undefined
                      }
                      onClick={() => gateMutation.mutate(option.value)}
                    >
                      Mark {option.label.toLowerCase()}
                    </button>
                  );
                })
            : null}
          {!manualStatusLocked && manualGateOptions.some((o) => o.value !== phase.gate_status) ? (
            <div className="my-1 border-t border-border" role="separator" />
          ) : null}
          <Link
            href="/project-one/gate-approvals"
            role="menuitem"
            className="block px-3 py-2 text-sm text-primary hover:bg-muted"
            onClick={() => setMenuOpen(false)}
          >
            Open approvals inbox
          </Link>
        </div>
      ) : null}
    </div>
  );
}

function GateActionPopover({
  title,
  description,
  notes,
  onNotesChange,
  notesPlaceholder,
  onCancel,
  actions,
  banner,
}: {
  title: string;
  description?: string;
  notes: string;
  onNotesChange: (value: string) => void;
  notesPlaceholder: string;
  onCancel: () => void;
  actions: ReactNode;
  banner?: ReactNode;
}) {
  return (
    <div className="absolute right-0 z-30 mt-1 w-64 rounded-lg border border-border bg-card p-3 shadow-md">
      <p className="text-sm font-medium text-foreground">{title}</p>
      {description ? <p className="mt-0.5 text-[11px] text-muted-foreground">{description}</p> : null}
      {banner ? <div className="mt-2">{banner}</div> : null}
      <textarea
        className="mt-2 h-16 w-full resize-none rounded-md border border-input bg-background px-2 py-1.5 text-xs"
        placeholder={notesPlaceholder}
        value={notes}
        onChange={(e) => onNotesChange(e.target.value)}
      />
      <div className="mt-2 flex justify-end gap-2">
        <Button type="button" variant="outline" size="sm" className="h-8 text-xs" onClick={onCancel}>
          Cancel
        </Button>
        {actions}
      </div>
    </div>
  );
}

export function GateStatusBadge({
  status,
  inReview = false,
}: {
  status: string | null;
  inReview?: boolean;
}) {
  if (inReview) {
    return (
      <span className="inline-flex shrink-0 rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-900 dark:bg-blue-950 dark:text-blue-100">
        In review
      </span>
    );
  }

  const value = status ?? "pending";
  const tone =
    value === "passed"
      ? "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200"
      : value === "failed"
        ? "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200"
        : value === "waived"
          ? "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
          : "bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100";

  return (
    <span className={`inline-flex shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium capitalize ${tone}`}>
      {value}
    </span>
  );
}
