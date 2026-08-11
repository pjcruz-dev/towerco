"use client";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";

type Props = {
  name: string;
  description: string;
  status: "draft" | "published";
  fields: EApprovalFormFieldInput[];
  steps: EApprovalWorkflowStepInput[];
  onNameChange: (v: string) => void;
  onDescriptionChange: (v: string) => void;
  onStatusChange: (v: "draft" | "published") => void;
  onFieldsChange: (fields: EApprovalFormFieldInput[]) => void;
  onStepsChange: (steps: EApprovalWorkflowStepInput[]) => void;
  approverOptions: { id: string; label: string }[];
};

const FIELD_TYPES = ["text", "textarea", "number", "date", "select", "file"];

export function EApprovalFormEditor({
  name,
  description,
  status,
  fields,
  steps,
  onNameChange,
  onDescriptionChange,
  onStatusChange,
  onFieldsChange,
  onStepsChange,
  approverOptions,
}: Props) {
  return (
    <div className="space-y-6">
      <div className="grid gap-4 md:grid-cols-2">
        <div className="space-y-2">
          <Label htmlFor="ea-form-name">Form name</Label>
          <Input id="ea-form-name" value={name} onChange={(e) => onNameChange(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label htmlFor="ea-form-status">Status</Label>
          <select
            id="ea-form-status"
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
            value={status}
            onChange={(e) => onStatusChange(e.target.value as "draft" | "published")}
          >
            <option value="draft">Draft</option>
            <option value="published">Published</option>
          </select>
        </div>
      </div>
      <div className="space-y-2">
        <Label htmlFor="ea-form-desc">Description</Label>
        <textarea
          id="ea-form-desc"
          className="min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          value={description}
          onChange={(e) => onDescriptionChange(e.target.value)}
        />
      </div>

      <section className="space-y-3 rounded-xl border border-border bg-card p-4">
        <div className="flex items-center justify-between">
          <h2 className="text-base font-medium">Fields</h2>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() =>
              onFieldsChange([
                ...fields,
                { type: "text", name: `field_${fields.length + 1}`, label: `Field ${fields.length + 1}` },
              ])
            }
          >
            Add field
          </Button>
        </div>
        {fields.map((field, index) => (
          <div key={index} className="grid gap-2 rounded-lg border border-border/60 p-3 md:grid-cols-4">
            <select
              className="h-10 rounded-md border border-input px-2 text-sm"
              value={field.type}
              onChange={(e) => {
                const next = [...fields];
                next[index] = { ...field, type: e.target.value };
                onFieldsChange(next);
              }}
            >
              {FIELD_TYPES.map((t) => (
                <option key={t} value={t}>
                  {t}
                </option>
              ))}
            </select>
            <Input
              placeholder="name"
              value={field.name}
              onChange={(e) => {
                const next = [...fields];
                next[index] = { ...field, name: e.target.value };
                onFieldsChange(next);
              }}
            />
            <Input
              placeholder="Label"
              value={field.label}
              onChange={(e) => {
                const next = [...fields];
                next[index] = { ...field, label: e.target.value };
                onFieldsChange(next);
              }}
            />
            <Button
              type="button"
              size="sm"
              variant="ghost"
              onClick={() => onFieldsChange(fields.filter((_, i) => i !== index))}
            >
              Remove
            </Button>
          </div>
        ))}
      </section>

      <section className="space-y-3 rounded-xl border border-border bg-card p-4">
        <div className="flex items-center justify-between">
          <h2 className="text-base font-medium">Approval steps</h2>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() =>
              onStepsChange([
                ...steps,
                {
                  type: "user",
                  approverId: approverOptions[0]?.id ?? "",
                  step_order: steps.length + 1,
                },
              ])
            }
          >
            Add step
          </Button>
        </div>
        {steps.map((step, index) => (
          <div key={index} className="grid gap-2 rounded-lg border border-border/60 p-3 md:grid-cols-4">
            <Input
              type="number"
              min={1}
              value={step.step_order ?? index + 1}
              onChange={(e) => {
                const next = [...steps];
                next[index] = { ...step, step_order: Number(e.target.value) };
                onStepsChange(next);
              }}
            />
            <select
              className="h-10 rounded-md border border-input px-2 text-sm"
              value={step.type}
              onChange={(e) => {
                const next = [...steps];
                next[index] = { ...step, type: e.target.value };
                onStepsChange(next);
              }}
            >
              <option value="user">Fixed user</option>
              <option value="field">From field (user id)</option>
            </select>
            <select
              className="h-10 rounded-md border border-input px-2 text-sm"
              value={step.approverId ?? ""}
              onChange={(e) => {
                const next = [...steps];
                next[index] = { ...step, approverId: e.target.value };
                onStepsChange(next);
              }}
            >
              <option value="">Select approver</option>
              {approverOptions.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.label}
                </option>
              ))}
            </select>
            <Button
              type="button"
              size="sm"
              variant="ghost"
              onClick={() => onStepsChange(steps.filter((_, i) => i !== index))}
            >
              Remove
            </Button>
          </div>
        ))}
      </section>
    </div>
  );
}
