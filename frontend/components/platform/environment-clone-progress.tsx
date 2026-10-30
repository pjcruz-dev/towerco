"use client";

import { Button } from "@/components/ui/button";
import type { EnvironmentCloneSnapshot } from "@/lib/api/modules/platform-api";

const CLONE_STEPS = [
  { id: "copying_database", label: "Copying database" },
  { id: "copying_files", label: "Copying files" },
  { id: "verifying", label: "Verifying" },
  { id: "ready", label: "Ready" },
] as const;

const ACTIVE = new Set(["queued", "copying_database", "copying_files", "verifying"]);

export function environmentCloneCanCopyAgain(
  clone: EnvironmentCloneSnapshot,
  viewingTenantId?: string,
): boolean {
  if (
    viewingTenantId != null &&
    viewingTenantId === clone.source_tenant_id &&
    viewingTenantId !== clone.target_tenant_id
  ) {
    return false;
  }
  if (ACTIVE.has(clone.status)) {
    return false;
  }
  if (clone.status === "ready") {
    return true;
  }
  return Boolean(clone.preserve_target) && (clone.status === "failed" || clone.status === "cancelled");
}

export function environmentCloneCopyAgainLabel(clone: EnvironmentCloneSnapshot): string {
  const source = clone.source_environment?.trim();
  if (source) {
    return `Copy again from ${source}`;
  }
  return "Copy again";
}

export function environmentCloneFilePercent(clone: EnvironmentCloneSnapshot): number {
  const total = clone.files_total ?? 0;
  if (total <= 0) {
    return 0;
  }
  return Math.min(100, Math.round((clone.files_copied / total) * 100));
}

export function environmentCloneOverallPercent(clone: EnvironmentCloneSnapshot): number {
  if (clone.status === "ready") {
    return 100;
  }
  if (clone.status === "verifying") {
    return 95;
  }
  if (clone.status === "copying_files" || clone.status === "failed") {
    return 20 + Math.round(environmentCloneFilePercent(clone) * 0.7);
  }
  if (clone.status === "copying_database") {
    return 20;
  }
  return 5;
}

function environmentCloneStepLabel(clone: EnvironmentCloneSnapshot): string {
  switch (clone.status) {
    case "copying_database":
      return "Copying database";
    case "copying_files":
      return "Copying files";
    case "verifying":
      return "Verifying";
    case "ready":
      return "Ready";
    case "failed":
      return "Failed";
    case "cancelled":
      return "Cancelled";
    case "discarded":
      return "Discarded";
    default:
      return "Queued";
  }
}

export function EnvironmentCloneProgress({
  clone,
  pending = false,
  phase,
  onRetry,
  onCancel,
  onDiscard,
  onCopyAgain,
  viewingTenantId,
}: {
  clone: EnvironmentCloneSnapshot;
  pending?: boolean;
  phase?: "removing" | "removed";
  onRetry?: () => void;
  onCancel?: () => void;
  onDiscard?: () => void;
  onCopyAgain?: () => void;
  viewingTenantId?: string;
}) {
  const failed = clone.status === "failed";
  const filePct = environmentCloneFilePercent(clone);
  const overallPct = environmentCloneOverallPercent(clone);
  const replacesExisting = clone.preserve_target === true;
  const removing =
    !replacesExisting &&
    (phase === "removing" || (Boolean(clone.cancel_requested) && ACTIVE.has(clone.status)));
  const removed =
    !replacesExisting &&
    (phase === "removed" || clone.status === "cancelled" || clone.status === "discarded");
  const stoppingRefresh =
    replacesExisting && Boolean(clone.cancel_requested) && ACTIVE.has(clone.status);
  const refreshStopped = replacesExisting && clone.status === "cancelled";
  const viewingSource = viewingTenantId != null && viewingTenantId === clone.source_tenant_id;
  const targetName = clone.environment?.trim() || "the other environment";
  const activeIndex = failed
    ? CLONE_STEPS.findIndex((step) => step.id === "verifying")
    : CLONE_STEPS.findIndex((step) => step.id === clone.status);
  const showFiles = clone.files_total !== null;

  return (
    <div className="space-y-3 text-sm text-foreground">
      {refreshStopped ? (
        <p>
          {clone.error_message ??
            "Copy stopped. This environment stays blocked until you copy again."}
        </p>
      ) : removing || removed ? (
        <ol className="space-y-1">
          <li className="text-foreground">Done · Stop requested</li>
          <li className="text-foreground">
            {removed ? "Done" : "Now"} · Removing the new environment
          </li>
          <li className={removed ? "text-foreground" : "text-muted-foreground"}>
            {removed ? "Done" : "Waiting"} · Database removed
          </li>
        </ol>
      ) : (
      <ol className="space-y-1">
        {CLONE_STEPS.map((step, index) => {
          const done = clone.status === "ready" || activeIndex > index;
          const current =
            clone.status !== "ready" &&
            (failed ? activeIndex === index : clone.status === "queued" ? index === 0 : activeIndex === index);
          const label = failed && index === activeIndex ? "Failed" : step.label;
          const mark = done ? "Done" : current ? "Now" : "Waiting";
          return (
            <li key={step.id} className={done || current || (failed && index === activeIndex) ? "text-foreground" : "text-muted-foreground"}>
              {mark}
              {" · "}
              {label}
            </li>
          );
        })}
      </ol>
      )}
      <div className="space-y-1">
        <div
          className="h-2 overflow-hidden rounded-full bg-muted"
          role="progressbar"
          aria-valuenow={removed ? 100 : removing ? 50 : overallPct}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-label="Environment copy"
        >
          <div className="h-full bg-primary" style={{ width: `${removed ? 100 : removing ? 50 : overallPct}%` }} />
        </div>
        <p className="text-sm tabular-nums text-foreground">
          {removed ? "Removed" : removing ? "Cancelling" : `${overallPct}% · ${environmentCloneStepLabel(clone)}`}
        </p>
        {showFiles ? (
          <p className="text-sm tabular-nums text-muted-foreground">
            {clone.files_copied} / {clone.files_total} files ({filePct}%)
          </p>
        ) : null}
      </div>
      {clone.snapshot_at ? (
        <p className="text-muted-foreground">Snapshot started {clone.snapshot_at}.</p>
      ) : null}
      {clone.counts ? (
        <p className="text-muted-foreground">
          Users {clone.counts.users ?? 0}, pending approvals {clone.counts.pending_approvals ?? 0}, documents{" "}
          {clone.counts.controlled_documents ?? 0}, automation rows {clone.counts.automation_schedules ?? 0}.
          {clone.status === "ready" && !viewingSource
            ? ` Automation is paused until you enable it (${clone.paused_schedules} schedule${clone.paused_schedules === 1 ? "" : "s"}).`
            : null}
        </p>
      ) : null}
      {failed ? (
        <p className="text-destructive">{clone.error_message ?? "The copy failed."}</p>
      ) : null}
      {removing ? (
        <p>The copy will stop, then this new environment and its database are deleted. Staging is not deleted.</p>
      ) : null}
      {removed ? (
        <p>The new environment and its database were removed. Staging was not changed.</p>
      ) : null}
      {stoppingRefresh ? (
        <p>The copy will stop. This environment stays blocked until a copy finishes. The source workspace is not deleted.</p>
      ) : null}
      {clone.status === "ready" && !removed ? (
        viewingSource ? (
          <p>
            This snapshot was copied into {targetName}. This workspace was not replaced.
          </p>
        ) : (
          <p>
            Database counts match the snapshot, and every copied attachment is on this environment. Work
            saved on the source after this snapshot is not included. Copy again replaces this environment
            and keeps its domain.
          </p>
        )
      ) : null}
      <div className="flex flex-wrap gap-2">
        {onCopyAgain && environmentCloneCanCopyAgain(clone, viewingTenantId) && !removing && !removed ? (
          <Button type="button" size="sm" disabled={pending} onClick={onCopyAgain}>
            {environmentCloneCopyAgainLabel(clone)}
          </Button>
        ) : null}
        {failed && !replacesExisting && onRetry ? (
          <Button type="button" size="sm" disabled={pending} onClick={onRetry}>
            Retry
          </Button>
        ) : null}
        {ACTIVE.has(clone.status) && !removing && !stoppingRefresh && onCancel ? (
          <Button type="button" size="sm" variant="outline" disabled={pending} onClick={onCancel}>
            Cancel copy
          </Button>
        ) : null}
        {["failed", "ready"].includes(clone.status) && !removing && onDiscard ? (
          <Button type="button" size="sm" variant="outline" disabled={pending} onClick={onDiscard}>
            Discard
          </Button>
        ) : null}
      </div>
    </div>
  );
}
