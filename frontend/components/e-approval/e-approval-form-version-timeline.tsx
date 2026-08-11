"use client";

import { useMutation } from "@tanstack/react-query";
import { useState } from "react";
import { GitCompare, History } from "lucide-react";

import {
  EApprovalFormRevisionDiffDialog,
  revisionSnapshotFromUnknown,
} from "@/components/e-approval/e-approval-form-revision-diff-dialog";
import { Button } from "@/components/ui/button";
import { restoreEApprovalFormRevision } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import type { EApprovalFormRevision } from "@/modules/e-approval/types";
import type { EApprovalFormSnapshot } from "@/modules/e-approval/form-revision-diff";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  formId: string;
  revisions: EApprovalFormRevision[];
  currentSnapshot: EApprovalFormSnapshot;
  submissionsCount?: number;
  onRestored?: () => void;
};

export function EApprovalFormVersionTimeline({
  formId,
  revisions,
  currentSnapshot,
  submissionsCount = 0,
  onRestored,
}: Props) {
  const push = useNotificationStore((s) => s.push);
  const sorted = [...revisions].sort((a, b) => (b.revision ?? 0) - (a.revision ?? 0));
  const [diffOpen, setDiffOpen] = useState(false);
  const [diffTitle, setDiffTitle] = useState("");
  const [diffDescription, setDiffDescription] = useState<string | undefined>();
  const [diffBefore, setDiffBefore] = useState<EApprovalFormSnapshot | null>(null);
  const [diffAfter, setDiffAfter] = useState<EApprovalFormSnapshot | null>(null);

  const restoreMutation = useMutation({
    mutationFn: (revision: number) => restoreEApprovalFormRevision(formId, revision),
    onSuccess: (result) => {
      push({
        level: "success",
        title: "Revision restored",
        message: result.warnings.length > 0 ? result.warnings.join(" ") : undefined,
      });
      onRestored?.();
    },
    onError: (e) => push({ level: "error", title: "Restore failed", message: getErrorMessage(e) }),
  });

  const openDiff = (rev: EApprovalFormRevision, compareTo: "current" | "previous") => {
    const afterSnap =
      compareTo === "current"
        ? currentSnapshot
        : revisionSnapshotFromUnknown(rev.snapshot as Record<string, unknown> | undefined);
    if (!afterSnap) {
      push({ level: "warning", title: "Cannot compare", message: "Snapshot data is missing for this revision." });
      return;
    }

    const revSnap = revisionSnapshotFromUnknown(rev.snapshot as Record<string, unknown> | undefined);
    if (!revSnap) {
      push({ level: "warning", title: "Cannot compare", message: "Snapshot data is missing for this revision." });
      return;
    }

    let beforeSnap: EApprovalFormSnapshot;
    let afterLabel: string;
    let beforeLabel: string;

    if (compareTo === "current") {
      beforeSnap = revSnap;
      afterLabel = "Current editor";
      beforeLabel = rev.label;
      setDiffBefore(beforeSnap);
      setDiffAfter(currentSnapshot);
    } else {
      const revIndex = sorted.findIndex((r) => r.revision === rev.revision);
      const older = sorted[revIndex + 1];
      const olderSnap = older
        ? revisionSnapshotFromUnknown(older.snapshot as Record<string, unknown> | undefined)
        : null;
      if (!olderSnap) {
        push({ level: "info", title: "First revision", message: "No older revision to compare against." });
        return;
      }
      beforeSnap = olderSnap;
      beforeLabel = older.label;
      afterLabel = rev.label;
      setDiffBefore(beforeSnap);
      setDiffAfter(revSnap);
    }

    setDiffTitle(`Changes: ${beforeLabel} → ${afterLabel}`);
    setDiffDescription(
      compareTo === "current"
        ? "Differences between this saved revision and your current editor state."
        : "Differences from the previous saved revision.",
    );
    setDiffOpen(true);
  };

  const confirmRestore = (rev: EApprovalFormRevision) => {
    const warnSubmissions =
      submissionsCount > 0
        ? ` This form has ${submissionsCount} submission${submissionsCount === 1 ? "" : "s"}; changing fields may affect integrations.`
        : "";
    const ok = window.confirm(
      `Restore "${rev.label}"? The editor will be replaced with that snapshot. Save again to record a new revision.${warnSubmissions}`,
    );
    if (ok) {
      restoreMutation.mutate(rev.revision);
    }
  };

  return (
    <>
      <aside className="space-y-3 rounded-xl border border-border bg-card p-4 xl:sticky xl:top-4">
        <div className="flex items-center gap-2">
          <History className="h-4 w-4 text-primary" />
          <h2 className="text-base font-medium">Version history</h2>
        </div>
        <p className="text-xs text-muted-foreground">
          Compare revisions to the current editor or the previous snapshot. Restore loads a snapshot (unsaved until you
          save).
        </p>
        {sorted.length === 0 ? (
          <p className="text-sm text-muted-foreground">No revisions yet. Save the form to create the first snapshot.</p>
        ) : (
          <ol className="relative space-y-0 border-l border-border pl-4">
            {sorted.map((rev, index) => (
              <li key={rev.revision} className="pb-4 last:pb-0">
                <span className="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border-2 border-background bg-primary" />
                <p className="text-sm font-medium text-foreground">{rev.label}</p>
                <p className="text-xs text-muted-foreground">
                  {rev.event === "published" ? "Published" : "Saved"} · {rev.field_count} fields · v{rev.schema_version}
                </p>
                {rev.saved_at ? (
                  <p className="text-[11px] text-muted-foreground">
                    {new Date(rev.saved_at).toLocaleString()} · {rev.saved_by?.name ?? "Unknown"}
                  </p>
                ) : null}
                <div className="mt-2 flex flex-wrap gap-1.5">
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-7 text-xs"
                    onClick={() => openDiff(rev, "current")}
                  >
                    <GitCompare className="mr-1 h-3 w-3" />
                    vs current
                  </Button>
                  {index < sorted.length - 1 ? (
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      className="h-7 text-xs"
                      onClick={() => openDiff(rev, "previous")}
                    >
                      vs previous
                    </Button>
                  ) : null}
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-7 text-xs"
                    disabled={restoreMutation.isPending}
                    onClick={() => confirmRestore(rev)}
                  >
                    {restoreMutation.isPending ? "Restoring…" : "Restore"}
                  </Button>
                </div>
              </li>
            ))}
          </ol>
        )}
      </aside>

      {diffBefore && diffAfter ? (
        <EApprovalFormRevisionDiffDialog
          open={diffOpen}
          onOpenChange={setDiffOpen}
          title={diffTitle}
          description={diffDescription}
          before={diffBefore}
          after={diffAfter}
        />
      ) : null}
    </>
  );
}
