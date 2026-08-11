"use client";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import {
  revisionRoutingLabel,
  type FormRevisionEditorSettings,
  type FormRevisionRouting,
} from "@/modules/e-approval/form-revision-config";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { isComposeFillableFieldType } from "@/modules/e-approval/form-compose-structural";

type Props = {
  value: FormRevisionEditorSettings;
  onChange: (next: FormRevisionEditorSettings) => void;
  fields: EApprovalFormFieldInput[];
  disabled?: boolean;
};

export function EApprovalFormRevisionSettingsCard({ value, onChange, fields, disabled }: Props) {
  const patch = (partial: Partial<FormRevisionEditorSettings>) => {
    onChange({ ...value, ...partial });
  };

  const fillableFields = fields.filter((field) => isComposeFillableFieldType(field.type));
  const resume = value.routing === "resume_returning_step";

  return (
    <EApprovalSectionCard
      title="Revision routing"
      description="Choose resume or full restart after the requestor revises and resubmits. Applies on the next resubmit — not to in-flight pending requests."
    >
      <div className="grid gap-4 md:grid-cols-2">
        <div className="space-y-2">
          <Label htmlFor="ea-revision-routing">After resubmit</Label>
          <Select
            id="ea-revision-routing"
            value={value.routing}
            disabled={disabled}
            onChange={(event) => patch({ routing: event.target.value as FormRevisionRouting })}
          >
            <option value="restart_from_start">{revisionRoutingLabel("restart_from_start")}</option>
            <option value="resume_returning_step">{revisionRoutingLabel("resume_returning_step")}</option>
          </Select>
          <p className="text-xs text-muted-foreground">
            {resume
              ? "Resume: return to the step that requested revision. Earlier approvals stay valid unless a material field changes."
              : "Full restart: rebuild the approval queue from step 1. Earlier approvals move to prior-cycle history."}
          </p>
        </div>

        <div className="flex items-start justify-between gap-3 rounded-lg border border-border bg-muted/20 px-3 py-3">
          <div className="space-y-1">
            <Label htmlFor="ea-revision-force">Allow approver to force full restart</Label>
            <p className="text-xs text-muted-foreground">
              When resume is enabled, shows “Require full re-approval” on the Decide tab so an
              approver can override and force step 1.
            </p>
          </div>
          <Switch
            id="ea-revision-force"
            checked={value.approverCanForceFullRestart}
            disabled={disabled}
            onCheckedChange={(checked) => patch({ approverCanForceFullRestart: Boolean(checked) })}
          />
        </div>
      </div>

      {resume ? (
        <div className="mt-4 space-y-2">
          <Label>Material fields (force restart if changed)</Label>
          <p className="text-xs text-muted-foreground">
            If the requestor changes any selected field on resubmit, the workflow restarts from step 1 even when resume
            routing is enabled.
          </p>
          {fillableFields.length === 0 ? (
            <p className="text-sm text-muted-foreground">Add fillable fields to the form to configure material fields.</p>
          ) : (
            <ul className="grid gap-2 sm:grid-cols-2">
              {fillableFields.map((field) => {
                const checked = value.materialFields.includes(field.name);
                return (
                  <li key={field.name}>
                    <label className="flex cursor-pointer items-center gap-2 rounded-md border border-border px-2 py-1.5 text-sm hover:bg-muted/40">
                      <Checkbox
                        checked={checked}
                        disabled={disabled}
                        onCheckedChange={(next) => {
                          const on = next === true;
                          patch({
                            materialFields: on
                              ? [...new Set([...value.materialFields, field.name])]
                              : value.materialFields.filter((name) => name !== field.name),
                          });
                        }}
                      />
                      <span className="truncate">
                        {field.label || field.name}
                        <span className="ml-1 font-mono text-[11px] text-muted-foreground">{field.name}</span>
                      </span>
                    </label>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      ) : null}
    </EApprovalSectionCard>
  );
}
