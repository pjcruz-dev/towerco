"use client";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type { FormBuilderCheckItem } from "@/modules/e-approval/form-builder-checklist";
import { checklistHasBlockingErrors } from "@/modules/e-approval/form-builder-checklist";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description: string;
  items: FormBuilderCheckItem[];
  confirmLabel: string;
  onConfirm: () => void;
  confirming?: boolean;
  upgradeConfirm?: {
    required: boolean;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
  };
};

export function EApprovalFormPublishChecklistDialog({
  open,
  onOpenChange,
  title,
  description,
  items,
  confirmLabel,
  onConfirm,
  confirming,
  upgradeConfirm,
}: Props) {
  const blocked =
    checklistHasBlockingErrors(items) || (upgradeConfirm?.required === true && !upgradeConfirm.checked);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
        <DialogBody>
          {items.length === 0 ? (
            <p className="text-sm text-muted-foreground">No issues found. You can continue.</p>
          ) : (
            <ul className="space-y-2 text-sm">
              {items.map((item, i) => (
                <li
                  key={i}
                  className={
                    item.level === "error"
                      ? "rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-red-950 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-100"
                      : "rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                  }
                >
                  {item.message}
                </li>
              ))}
            </ul>
          )}
          {upgradeConfirm?.required ? (
            <label className="mt-4 flex cursor-pointer items-start gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm">
              <Checkbox
                className="mt-0.5"
                checked={upgradeConfirm.checked}
                onCheckedChange={(v) => upgradeConfirm.onCheckedChange(v === true)}
              />
              <span>
                I understand that open submissions keep their original workflow and that structural changes can affect
                in-flight approvals.
              </span>
            </label>
          ) : null}
        </DialogBody>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={confirming}>
            Cancel
          </Button>
          <Button type="button" onClick={onConfirm} disabled={blocked || confirming}>
            {confirming ? "Working…" : confirmLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
