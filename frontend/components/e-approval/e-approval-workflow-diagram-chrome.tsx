"use client";

import type { ReactNode } from "react";

import { EApprovalApproverNameSignature } from "@/components/e-approval/e-approval-approver-name-signature";
import { EApprovalStatusBadge } from "@/components/e-approval/e-approval-status-badge";
import type { WorkflowDiagramBand, WorkflowDiagramNode } from "@/modules/e-approval/workflow-path-diagram";
import { cn } from "@/lib/utils";

export function WorkflowDiagramConnector({ muted = false }: { muted?: boolean }) {
  return (
    <div className="flex justify-center py-1" aria-hidden>
      <div
        className={cn(
          "h-5 w-px",
          muted ? "border-l border-dashed border-border" : "bg-sky-500/70 dark:bg-sky-400/60",
        )}
      />
    </div>
  );
}

export function WorkflowDiagramTerminal({ label }: { label: string }) {
  return (
    <div className="rounded-md border border-border bg-card px-3 py-1 text-xs font-medium text-muted-foreground">
      {label}
    </div>
  );
}

type NodeCardProps = {
  node: WorkflowDiagramNode;
  selected?: boolean;
  onSelect?: () => void;
  statusFallback?: ReactNode;
  /** Wider cards for multi-case / parallel bands on large screens. */
  wide?: boolean;
};

export function WorkflowDiagramNodeCard({
  node,
  selected,
  onSelect,
  statusFallback,
  wide = false,
}: NodeCardProps) {
  const isSkipped = node.kind === "skipped";
  const status = node.status;
  const interactive = typeof onSelect === "function";

  const titleName =
    !isSkipped && node.title ? (
      interactive ? (
        <span> · {node.title}</span>
      ) : (
        <>
          {" · "}
          <EApprovalApproverNameSignature name={node.title} signature={node.signature} />
        </>
      )
    ) : null;

  const content = (
    <>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <p className="font-medium leading-snug">
          Step {node.stepOrder}
          {titleName}
        </p>
        {isSkipped ? (
          <span className="text-xs text-muted-foreground">Skipped</span>
        ) : status ? (
          <EApprovalStatusBadge status={status} kind="approval" />
        ) : (
          (statusFallback ?? <span className="text-xs text-muted-foreground">Not started</span>)
        )}
      </div>
      {isSkipped ? <p className="mt-1 text-foreground/80">{node.title}</p> : null}
      {node.subtitle ? <p className="mt-1 text-xs text-muted-foreground">{node.subtitle}</p> : null}
      {node.detail ? <p className="mt-1 text-xs text-muted-foreground">{node.detail}</p> : null}
      {node.warning ? (
        <p className="mt-1 text-xs text-amber-700 dark:text-amber-300">{node.warning}</p>
      ) : null}
    </>
  );

  const className = cn(
    "flex-1 rounded-lg border px-3 py-2 text-left text-sm shadow-sm",
    wide ? "min-w-[13rem] max-w-[24rem]" : "min-w-[12rem] max-w-[20rem]",
    isSkipped
      ? "border-dashed border-border bg-muted/20 text-muted-foreground"
      : "border-border bg-card text-foreground",
    status === "pending" && "border-amber-300 dark:border-amber-800",
    status === "approved" && "border-green-300 dark:border-green-800",
    status === "rejected" && "border-destructive/40",
    status === "returned" &&
      "border-dashed border-amber-300 bg-amber-500/5 text-foreground dark:border-amber-800",
    (status === "invalidated" || status === "superseded" || status === "cancelled") &&
      "border-dashed border-border bg-muted/20 text-muted-foreground",
    selected && "ring-2 ring-sky-500/60 border-sky-400 dark:border-sky-600",
    interactive && !isSkipped && "cursor-pointer hover:border-sky-300 dark:hover:border-sky-700",
  );

  if (interactive) {
    return (
      <button type="button" className={className} onClick={onSelect} aria-pressed={selected}>
        {content}
      </button>
    );
  }

  return <div className={className}>{content}</div>;
}

type BandBlockProps = {
  band: WorkflowDiagramBand;
  renderNode?: (node: WorkflowDiagramNode) => ReactNode;
  trailing?: ReactNode;
  className?: string;
};

export function WorkflowDiagramBandBlock({ band, renderNode, trailing, className }: BandBlockProps) {
  const parallel = band.nodes.length > 1;

  return (
    <div className={cn("flex w-full flex-col items-center", className)}>
      {band.bandLabel ? (
        <p
          className={cn(
            "mb-2 text-center text-xs font-medium",
            band.kind === "skipped"
              ? "text-muted-foreground"
              : "text-violet-800 dark:text-violet-200",
          )}
        >
          Step {band.stepOrder} — {band.bandLabel}
        </p>
      ) : null}
      <div
        className={cn(
          "flex max-w-full flex-wrap items-stretch justify-center gap-2",
          // Hug cards so parallel bands do not stretch empty margins across the panel.
          parallel && "w-fit",
          parallel &&
            band.kind === "run" &&
            "rounded-xl border border-violet-200/80 bg-violet-50/40 p-2 dark:border-violet-900/50 dark:bg-violet-950/20",
        )}
      >
        {band.nodes.map((node) =>
          renderNode ? (
            <div key={node.id} className="contents">
              {renderNode(node)}
            </div>
          ) : (
            <WorkflowDiagramNodeCard key={node.id} node={node} wide={parallel} />
          ),
        )}
        {trailing}
      </div>
    </div>
  );
}

export function WorkflowDiagramShell({
  title,
  description,
  legend,
  actions,
  children,
  className,
}: {
  title: string;
  description?: string;
  legend?: ReactNode;
  actions?: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "rounded-xl border border-border/70 bg-muted/10 px-3 py-4 sm:px-4",
        className,
      )}
    >
      <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="text-xs font-medium text-foreground">{title}</p>
          {description ? <p className="mt-0.5 text-[11px] text-muted-foreground">{description}</p> : null}
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {legend}
          {actions}
        </div>
      </div>
      <div className="flex w-full flex-col items-center overflow-visible px-1 sm:px-2">
        {children}
      </div>
    </div>
  );
}
