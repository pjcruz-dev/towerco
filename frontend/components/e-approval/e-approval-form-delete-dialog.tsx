"use client";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  formName: string;
  submissionsCount: number;
  onConfirm: () => void;
  confirming?: boolean;
};

export function EApprovalFormDeleteDialog({
  open,
  onOpenChange,
  formName,
  submissionsCount,
  onConfirm,
  confirming,
}: Props) {
  const blocked = submissionsCount > 0;
  const trimmedName = formName.trim() || "this form";

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete form</DialogTitle>
          <DialogDescription>
            {blocked
              ? "Forms with existing submissions cannot be deleted."
              : `Permanently remove "${trimmedName}" and its workflow configuration.`}
          </DialogDescription>
        </DialogHeader>
        <DialogBody>
          {blocked ? (
            <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
              This form has {submissionsCount} submission{submissionsCount === 1 ? "" : "s"}. Archive or retain it
              instead — deletion is only allowed when no submissions exist.
            </p>
          ) : (
            <p className="text-sm text-muted-foreground">
              This action cannot be undone. Field definitions, workflow steps, print layout, and public links for this
              form will be removed.
            </p>
          )}
        </DialogBody>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={confirming}>
            Cancel
          </Button>
          <Button type="button" variant="destructive" onClick={onConfirm} disabled={blocked || confirming}>
            {confirming ? "Deleting…" : "Delete form"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
