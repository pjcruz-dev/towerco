"use client";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import type { FormWorkflowOptionSettings } from "@/modules/e-approval/form-workflow-options";

type Props = {
  value: FormWorkflowOptionSettings;
  onChange: (next: FormWorkflowOptionSettings) => void;
  disabled?: boolean;
};

export function EApprovalFormWorkflowOptionsCard({ value, onChange, disabled }: Props) {
  return (
    <EApprovalSectionCard
      title="Workflow options"
      description="Both are off until you turn them on. They apply to requests submitted after you save."
    >
      <div className="flex items-start justify-between gap-3 rounded-lg border border-border bg-muted/20 px-3 py-3">
        <div className="space-y-1">
          <Label htmlFor="ea-optional-approver">Optional approver</Label>
          <p className="text-xs text-muted-foreground">
            When a step is marked Optional, the approver before it can finish the request or send it into that step. While this is off, every step on the path runs.
          </p>
        </div>
        <Switch
          id="ea-optional-approver"
          checked={value.optionalApprover}
          disabled={disabled}
          onCheckedChange={(checked) => onChange({ ...value, optionalApprover: Boolean(checked) })}
        />
      </div>
      <div className="mt-3 flex items-start justify-between gap-3 rounded-lg border border-border bg-muted/20 px-3 py-3">
        <div className="min-w-0 space-y-1">
          <Label htmlFor="ea-approved-files">Send approved files</Label>
          <p className="text-xs text-muted-foreground">
            When the request is fully approved, the final approver chooses who receives the current files. This runs whether or not the optional step was used.
          </p>
        </div>
        <Switch
          id="ea-approved-files"
          checked={value.sendApprovedFiles}
          disabled={disabled}
          onCheckedChange={(checked) => onChange({ ...value, sendApprovedFiles: Boolean(checked) })}
        />
      </div>
    </EApprovalSectionCard>
  );
}
