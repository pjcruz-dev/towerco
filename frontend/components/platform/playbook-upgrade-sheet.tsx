"use client";

import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tenantLabel: string;
  currentVersion: string | null;
  targetVersion: string;
  isPending: boolean;
  onConfirm: () => void;
};

export function PlaybookUpgradeSheet({
  open,
  onOpenChange,
  tenantLabel,
  currentVersion,
  targetVersion,
  isPending,
  onConfirm,
}: Props) {
  const isUpgrade = Boolean(currentVersion && currentVersion !== targetVersion);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full sm:max-w-md">
        <SheetHeader>
          <SheetTitle>{isUpgrade ? "Upgrade rollout playbook" : "Assign rollout playbook"}</SheetTitle>
          <SheetDescription>
            {tenantLabel} will use playbook v{targetVersion} for new rollouts.
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-3 px-4 py-2 text-sm text-muted-foreground">
          <p>
            Existing rollouts keep their original playbook version and timeline rules. Only newly created rollouts
            inherit v{targetVersion}.
          </p>
          {currentVersion ? (
            <p>
              Current assignment:{" "}
              <span className="font-mono text-foreground">v{currentVersion}</span>
              {" → "}
              <span className="font-mono text-foreground">v{targetVersion}</span>
            </p>
          ) : (
            <p>No playbook is assigned yet. This sets the tenant default for new rollouts.</p>
          )}
        </div>

        <SheetFooter className="gap-2 sm:gap-0">
          <Button type="button" variant="outline" disabled={isPending} onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={isPending} onClick={onConfirm}>
            {isPending ? "Applying…" : isUpgrade ? "Upgrade playbook" : "Assign playbook"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
