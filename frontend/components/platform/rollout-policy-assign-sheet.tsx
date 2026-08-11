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
import type { PlatformRolloutPolicyBundle } from "@/lib/api/modules/platform-api";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tenantLabel: string;
  currentPolicyCode: string | null;
  currentPolicyName: string | null;
  targetPolicyCode: string;
  targetPolicyName: string;
  targetPlaybookVersion: string | null;
  isPending: boolean;
  onConfirm: () => void;
  publishedPolicies?: PlatformRolloutPolicyBundle[];
  selectedPolicyId?: string;
  onSelectedPolicyIdChange?: (policyId: string) => void;
};

export function RolloutPolicyAssignSheet({
  open,
  onOpenChange,
  tenantLabel,
  currentPolicyCode,
  currentPolicyName,
  targetPolicyCode,
  targetPolicyName,
  targetPlaybookVersion,
  isPending,
  onConfirm,
  publishedPolicies = [],
  selectedPolicyId = "",
  onSelectedPolicyIdChange,
}: Props) {
  const isChange = Boolean(currentPolicyCode && currentPolicyCode !== targetPolicyCode);
  const showPicker = publishedPolicies.length > 0 && onSelectedPolicyIdChange !== undefined;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full sm:max-w-md">
        <SheetHeader>
          <SheetTitle>{isChange ? "Change rollout policy" : "Assign rollout policy"}</SheetTitle>
          <SheetDescription>
            {tenantLabel} — choose a published policy bundle for new rollouts.
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-4 px-4 py-2">
          {showPicker ? (
            <label className="block space-y-1.5 text-sm">
              <span className="font-medium text-foreground">Policy bundle</span>
              <select
                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={selectedPolicyId}
                onChange={(event) => onSelectedPolicyIdChange(event.target.value)}
                disabled={isPending}
              >
                {publishedPolicies.map((policy) => (
                  <option key={policy.id} value={policy.id}>
                    {policy.code}
                    {policy.playbook_version ? ` (v${policy.playbook_version})` : ""}
                  </option>
                ))}
              </select>
            </label>
          ) : null}

        <div className="space-y-3 text-sm text-muted-foreground">
          <p>
            The policy bundle includes playbook timeline, SLA windows, hidden phases, and default gate approval chains.
            Existing rollouts keep their original snapshot; only newly created rollouts inherit this policy.
          </p>
          {targetPlaybookVersion ? (
            <p>
              Playbook base: <span className="font-mono text-foreground">v{targetPlaybookVersion}</span>
            </p>
          ) : null}
          {currentPolicyCode ? (
            <p>
              Current:{" "}
              <span className="font-mono text-foreground">{currentPolicyCode}</span>
              {currentPolicyName ? ` (${currentPolicyName})` : ""}
              {" → "}
              <span className="font-mono text-foreground">{targetPolicyCode}</span>
            </p>
          ) : (
            <p>No rollout policy is assigned yet.</p>
          )}
        </div>
        </div>

        <SheetFooter className="gap-2 sm:gap-0">
          <Button type="button" variant="outline" disabled={isPending} onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={isPending} onClick={onConfirm}>
            {isPending ? "Applying…" : isChange ? "Change policy" : "Assign policy"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
