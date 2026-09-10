"use client";

import {
  CheckCircle2,
  CircleDot,
  CreditCard,
  FileText,
  UserRound,
  type LucideIcon,
} from "lucide-react";
import { Fragment } from "react";

import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";

export type WorkflowStepShowItem = {
  key: string;
  label: string;
  description?: string;
  /** completed | current | upcoming | cancelled | skipped */
  state: "completed" | "current" | "upcoming" | "cancelled" | "skipped";
  /** Primary approver display name for this step */
  approverName?: string | null;
  /** Parallel / multi-approver names */
  approverNames?: string[];
  statusLabel?: string;
};

const STEP_ICONS: LucideIcon[] = [FileText, UserRound, CreditCard, CheckCircle2, CircleDot];

function iconForIndex(index: number): LucideIcon {
  return STEP_ICONS[index % STEP_ICONS.length] ?? FileText;
}

function formatApproverLine(step: WorkflowStepShowItem): string | null {
  const names =
    step.approverNames && step.approverNames.length > 0
      ? step.approverNames
      : step.approverName
        ? [step.approverName]
        : [];
  if (names.length === 0) return null;
  if (names.length === 1) return names[0] ?? null;
  if (names.length === 2) return `${names[0]} + ${names[1]}`;
  return `${names[0]} +${names.length - 1}`;
}

function statusForStep(step: WorkflowStepShowItem): string {
  if (step.statusLabel) return step.statusLabel;
  switch (step.state) {
    case "completed":
      return "Approved";
    case "current":
      return "Pending";
    case "cancelled":
      return "Cancelled";
    case "skipped":
      return "Skipped";
    default:
      return "Upcoming";
  }
}

function compactCaption(steps: WorkflowStepShowItem[]): { progress: string; waiting: string | null } {
  const actionable = steps.filter((step) => step.state !== "skipped" && step.state !== "cancelled");
  const current = steps.find((step) => step.state === "current");
  const currentIndex = Math.max(
    0,
    steps.findIndex((step) => step.state === "current"),
  );
  const doneCount = steps.filter((step) => step.state === "completed").length;
  const allActionableDone =
    actionable.length > 0 && actionable.every((step) => step.state === "completed");

  const progress = current
    ? `Step ${currentIndex + 1} of ${steps.length}`
    : allActionableDone
      ? `Complete · ${doneCount} steps`
      : `${doneCount}/${steps.length} steps`;

  if (current) {
    const who = formatApproverLine(current);
    return {
      progress,
      waiting: who ? `Waiting on ${who}` : "Waiting on approver",
    };
  }

  if (allActionableDone) {
    const lastApproved = [...steps].reverse().find((step) => step.state === "completed");
    const who = lastApproved ? formatApproverLine(lastApproved) : null;
    return {
      progress,
      waiting: who ? `Approved · ${who}` : "Approved",
    };
  }

  return { progress, waiting: null };
}

function tooltipLines(steps: WorkflowStepShowItem[]): string[] {
  return steps.map((step, index) => {
    const who = formatApproverLine(step);
    const status = statusForStep(step);
    const order = index + 1;
    return who ? `Step ${order} · ${who} · ${status}` : `Step ${order} · ${status}`;
  });
}

/** Prefer "Step N" over technical field labels like "Approver field: approver_1". */
export function humanWorkflowStepLabel(
  raw: string | null | undefined,
  order: number,
  approverName?: string | null,
): string {
  const cleaned = (raw ?? "").trim();
  const isTechnical =
    cleaned === "" ||
    /^approver field:/i.test(cleaned) ||
    /^approver[_-]?\d+$/i.test(cleaned) ||
    /^field:/i.test(cleaned);

  if (approverName?.trim()) {
    return `Step ${order} · ${approverName.trim()}`;
  }

  if (isTechnical) {
    return `Step ${order}`;
  }

  return cleaned;
}

type Props = {
  steps: WorkflowStepShowItem[];
  /** compact = table/gallery; full = detail header (Vuexy-style) */
  variant?: "compact" | "full";
  className?: string;
  emptyLabel?: string;
};

/**
 * Vuexy-inspired horizontal workflow step show for E-Forms submissions.
 * Compact: bar + "Step X of Y" + current approver; hover shows full trail.
 * Full: aligns with vertical path semantics (Approved / Pending / Skipped).
 */
export function EApprovalWorkflowStepShow({
  steps,
  variant = "full",
  className,
  emptyLabel = "No workflow steps",
}: Props) {
  if (steps.length === 0) {
    return <span className="text-sm text-muted-foreground">{emptyLabel}</span>;
  }

  if (variant === "compact") {
    const { progress, waiting } = compactCaption(steps);
    const lines = tooltipLines(steps);

    return (
      <Tooltip>
        <TooltipTrigger
          delay={250}
          render={
            <button
              type="button"
              className={cn(
                "min-w-[9rem] max-w-[14rem] cursor-default space-y-1 rounded-sm text-left outline-none focus-visible:ring-2 focus-visible:ring-ring",
                className,
              )}
            />
          }
        >
          <span className="flex items-center gap-1">
            {steps.map((step) => (
              <span
                key={step.key}
                className={cn(
                  "h-1.5 flex-1 rounded-full",
                  step.state === "completed" && "bg-emerald-500",
                  step.state === "current" && "bg-primary",
                  step.state === "upcoming" && "bg-muted",
                  (step.state === "cancelled" || step.state === "skipped") && "bg-muted-foreground/25",
                )}
                aria-hidden
              />
            ))}
          </span>
          <span className="block text-[11px] tabular-nums text-muted-foreground">{progress}</span>
          {waiting ? (
            <span className="block truncate text-[11px] font-medium text-foreground">{waiting}</span>
          ) : null}
        </TooltipTrigger>
        <TooltipContent
          side="top"
          className="max-w-[min(22rem,calc(100vw-2rem))] flex-col items-start gap-1.5 p-2.5 text-left"
        >
          <p className="text-[11px] font-medium text-background/80">Approval trail</p>
          <ul className="space-y-0.5">
            {lines.map((line) => (
              <li key={line} className="text-xs leading-snug text-background">
                {line}
              </li>
            ))}
          </ul>
        </TooltipContent>
      </Tooltip>
    );
  }

  return (
    <nav
      aria-label="Workflow steps"
      className={cn("flex flex-wrap items-center gap-2 sm:gap-3", className)}
    >
      {steps.map((step, index) => {
        const Icon = iconForIndex(index);
        const active = step.state === "current";
        const done = step.state === "completed";
        const cancelled = step.state === "cancelled";
        const skipped = step.state === "skipped";
        const who = formatApproverLine(step);
        const status = statusForStep(step);
        // Title already includes the approver ("Step 1 · Name") — keep subtitle status-only.
        const nameAlreadyInLabel =
          who != null && step.label.toLowerCase().includes(who.toLowerCase());
        const description =
          who && !nameAlreadyInLabel && (done || active || cancelled)
            ? `${status} · ${who}`
            : status;

        return (
          <Fragment key={step.key}>
            {index > 0 ? (
              <span className="hidden text-muted-foreground/50 sm:inline" aria-hidden>
                ›
              </span>
            ) : null}
            <div className={cn("flex min-w-0 items-center gap-2.5", skipped && "opacity-70")}>
              <span
                className={cn(
                  "flex size-9 shrink-0 items-center justify-center rounded-lg shadow-sm",
                  active && "bg-primary text-primary-foreground",
                  done && !active && "bg-emerald-500/15 text-emerald-700 dark:text-emerald-400",
                  skipped && "border border-dashed border-border bg-muted/40 text-muted-foreground",
                  cancelled && "bg-muted text-muted-foreground/60",
                  !active && !done && !cancelled && !skipped && "bg-muted text-muted-foreground",
                )}
              >
                <Icon className="size-4" aria-hidden />
              </span>
              <div className="min-w-0">
                <p
                  className={cn(
                    "truncate text-sm font-medium",
                    active ? "text-foreground" : "text-muted-foreground",
                  )}
                >
                  {step.label}
                </p>
                {description ? (
                  <p className="truncate text-xs text-muted-foreground">{description}</p>
                ) : null}
              </div>
            </div>
          </Fragment>
        );
      })}
    </nav>
  );
}

export type WorkflowStepSummaryRow = {
  step_order: number;
  label?: string | null;
  state?: string | null;
  status_label?: string | null;
  approver_name?: string | null;
  approver_names?: string[];
};

/** Prefer API workflow_steps (with approver names); fall back to index/count. */
export function buildWorkflowStepShowItems(input: {
  currentStep: number;
  stepCount?: number | null;
  status?: string | null;
  stepLabels?: string[];
  workflowSteps?: WorkflowStepSummaryRow[] | null;
}): WorkflowStepShowItem[] {
  if (input.workflowSteps && input.workflowSteps.length > 0) {
    return input.workflowSteps.map((step, index) => {
      const order = step.step_order || index + 1;
      const stateRaw = (step.state ?? "").toLowerCase();
      const state: WorkflowStepShowItem["state"] =
        stateRaw === "completed" ||
        stateRaw === "current" ||
        stateRaw === "upcoming" ||
        stateRaw === "cancelled" ||
        stateRaw === "skipped"
          ? stateRaw
          : "upcoming";
      const names = (step.approver_names ?? []).filter(Boolean);
      const primary = step.approver_name ?? names[0] ?? null;
      return {
        key: `api-step-${order}`,
        label: humanWorkflowStepLabel(step.label, order, primary),
        description: statusForStep({
          key: "",
          label: "",
          state,
          statusLabel: step.status_label ?? undefined,
        }),
        state,
        statusLabel: step.status_label ?? undefined,
        approverName: primary,
        approverNames: names.length > 0 ? names : primary ? [primary] : [],
      };
    });
  }

  const status = (input.status ?? "").toLowerCase();
  const total = Math.max(
    input.stepCount && input.stepCount > 0 ? input.stepCount : 0,
    input.currentStep > 0 ? input.currentStep : 1,
    1,
  );
  const current = Math.max(0, input.currentStep);
  const approved = status === "approved";
  const cancelled = status === "cancelled";
  const returned = status === "returned" || status === "rejected";

  return Array.from({ length: total }, (_, index) => {
    const order = index + 1;
    let state: WorkflowStepShowItem["state"] = "upcoming";
    if (cancelled) {
      state = order < current ? "completed" : "cancelled";
    } else if (approved) {
      state = "completed";
    } else if (returned) {
      state = order < current ? "completed" : order === current ? "current" : "upcoming";
    } else if (current <= 0) {
      state = "upcoming";
    } else if (order < current) {
      state = "completed";
    } else if (order === current) {
      state = "current";
    }
    return {
      key: `step-${order}`,
      label: humanWorkflowStepLabel(input.stepLabels?.[index], order),
      description: statusForStep({ key: "", label: "", state }),
      state,
      statusLabel: statusForStep({ key: "", label: "", state }),
    };
  });
}

/** Map workflow preview resolved steps into step-show items. */
export function workflowPreviewToStepShowItems(
  steps: Array<{
    step_order?: number | null;
    label?: string | null;
    runtime_status?: string | null;
    resolved_user_name?: string | null;
    runtime_approver?: { name?: string | null } | null;
  }>,
  submissionStatus?: string | null,
  skippedSteps?: Array<{ step_order?: number | null; label?: string | null }> | null,
): WorkflowStepShowItem[] {
  const status = (submissionStatus ?? "").toLowerCase();
  const approved = status === "approved";
  const cancelled = status === "cancelled";

  const resolved = steps.map((step, index) => {
    const order = step.step_order ?? index + 1;
    const runtime = (step.runtime_status ?? "").toLowerCase();
    let state: WorkflowStepShowItem["state"] = "upcoming";

    // Skipped must never render as completed/green "Done".
    if (runtime === "skipped") {
      state = "skipped";
    } else if (runtime === "approved") {
      state = "completed";
    } else if (cancelled || runtime === "cancelled") {
      state = "cancelled";
    } else if (runtime === "pending" || runtime === "waiting" || runtime === "in_progress") {
      state = "current";
    } else if (runtime === "rejected" || runtime === "returned") {
      state = "current";
    } else if (approved) {
      state = "completed";
    }

    const approverName =
      step.runtime_approver?.name?.trim() || step.resolved_user_name?.trim() || null;

    return {
      key: `preview-${order}`,
      label: humanWorkflowStepLabel(step.label, order, state === "skipped" ? null : approverName),
      description: undefined,
      state,
      statusLabel:
        state === "current"
          ? "Pending"
          : state === "completed"
            ? "Approved"
            : state === "cancelled"
              ? "Cancelled"
              : state === "skipped"
                ? "Skipped"
                : "Upcoming",
      approverName: state === "skipped" ? null : approverName,
      approverNames: state === "skipped" || !approverName ? [] : [approverName],
    } satisfies WorkflowStepShowItem;
  });

  const existingOrders = new Set(resolved.map((step) => step.key));
  const extras = (skippedSteps ?? []).flatMap((step, index) => {
    const order = step.step_order ?? index + 1;
    const key = `preview-${order}`;
    if (existingOrders.has(key)) {
      return [];
    }
    return [
      {
        key,
        label: humanWorkflowStepLabel(step.label, order),
        state: "skipped" as const,
        statusLabel: "Skipped",
        approverName: null,
        approverNames: [],
      } satisfies WorkflowStepShowItem,
    ];
  });

  return [...resolved, ...extras].sort((a, b) => {
    const aOrder = Number(a.key.replace(/\D/g, "")) || 0;
    const bOrder = Number(b.key.replace(/\D/g, "")) || 0;
    return aOrder - bOrder;
  });
}
