"use client";

import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  buildFormRevisionDiff,
  snapshotFromRevisionPayload,
  type EApprovalFormSnapshot,
  type RevisionDiffItem,
} from "@/modules/e-approval/form-revision-diff";
import { cn } from "@/lib/utils";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description?: string;
  before: EApprovalFormSnapshot;
  after: EApprovalFormSnapshot;
};

function DiffRow({ item }: { item: RevisionDiffItem }) {
  return (
    <li
      className={cn(
        "rounded-lg border px-3 py-2 text-sm",
        item.kind === "added" && "border-emerald-200 bg-emerald-50/80 dark:border-emerald-900/40 dark:bg-emerald-950/30",
        item.kind === "removed" && "border-red-200 bg-red-50/80 dark:border-red-900/40 dark:bg-red-950/30",
        item.kind === "changed" && "border-amber-200 bg-amber-50/80 dark:border-amber-900/40 dark:bg-amber-950/30",
      )}
    >
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{item.kind}</span>
        <span className="font-medium text-foreground">{item.label}</span>
        <span className="text-xs text-muted-foreground">({item.section})</span>
      </div>
      {item.detail ? <p className="mt-1 text-xs text-muted-foreground">{item.detail}</p> : null}
    </li>
  );
}

export function EApprovalFormRevisionDiffDialog({ open, onOpenChange, title, description, before, after }: Props) {
  const diff = buildFormRevisionDiff(before, after);
  const grouped = {
    form: diff.filter((d) => d.section === "form"),
    field: diff.filter((d) => d.section === "field"),
    workflow: diff.filter((d) => d.section === "workflow"),
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[min(90vh,720px)] w-[min(calc(100vw-1rem),640px)]">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          {description ? <DialogDescription>{description}</DialogDescription> : null}
        </DialogHeader>
        <DialogBody className="min-h-0 space-y-4 overflow-y-auto">
          {diff.length === 0 ? (
            <p className="text-sm text-muted-foreground">No differences between these versions.</p>
          ) : (
            <>
              {grouped.form.length > 0 ? (
                <section className="space-y-2">
                  <h3 className="text-xs font-medium text-muted-foreground">Form</h3>
                  <ul className="space-y-2">{grouped.form.map((item) => <DiffRow key={item.key} item={item} />)}</ul>
                </section>
              ) : null}
              {grouped.field.length > 0 ? (
                <section className="space-y-2">
                  <h3 className="text-xs font-medium text-muted-foreground">Fields</h3>
                  <ul className="space-y-2">{grouped.field.map((item) => <DiffRow key={item.key} item={item} />)}</ul>
                </section>
              ) : null}
              {grouped.workflow.length > 0 ? (
                <section className="space-y-2">
                  <h3 className="text-xs font-medium text-muted-foreground">Workflow</h3>
                  <ul className="space-y-2">
                    {grouped.workflow.map((item) => (
                      <DiffRow key={item.key} item={item} />
                    ))}
                  </ul>
                </section>
              ) : null}
            </>
          )}
        </DialogBody>
      </DialogContent>
    </Dialog>
  );
}

export function revisionSnapshotFromUnknown(
  snapshot: Record<string, unknown> | undefined,
): EApprovalFormSnapshot | null {
  return snapshotFromRevisionPayload(snapshot);
}
