import { formatEApprovalFieldTypeLabel } from "@/modules/e-approval/field-types";
import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";

export type EApprovalFormSnapshot = {
  name: string;
  description?: string | null;
  status?: string;
  fields: EApprovalFormFieldInput[];
  steps: EApprovalWorkflowStepInput[];
};

export type RevisionDiffItem = {
  kind: "added" | "removed" | "changed";
  section: "form" | "field" | "workflow";
  key: string;
  label: string;
  detail?: string;
};

export function snapshotFromRevisionPayload(payload: Record<string, unknown> | null | undefined): EApprovalFormSnapshot | null {
  if (!payload || typeof payload !== "object") {
    return null;
  }

  const fields = Array.isArray(payload.fields) ? (payload.fields as EApprovalFormFieldInput[]) : [];
  const steps = Array.isArray(payload.steps) ? (payload.steps as EApprovalWorkflowStepInput[]) : [];

  return {
    name: String(payload.name ?? ""),
    description: typeof payload.description === "string" ? payload.description : null,
    status: typeof payload.status === "string" ? payload.status : undefined,
    fields,
    steps,
  };
}

function fieldSignature(field: EApprovalFormFieldInput): string {
  return JSON.stringify({
    type: field.type,
    label: field.label,
    validation: field.validation ?? null,
    options: field.options ?? null,
  });
}

function stepSignature(step: EApprovalWorkflowStepInput, index: number): string {
  return JSON.stringify({
    order: step.step_order ?? index + 1,
    type: step.type,
    approverId: step.approverId ?? "",
  });
}

export function buildFormRevisionDiff(before: EApprovalFormSnapshot, after: EApprovalFormSnapshot): RevisionDiffItem[] {
  const items: RevisionDiffItem[] = [];

  if (before.name.trim() !== after.name.trim()) {
    items.push({
      kind: "changed",
      section: "form",
      key: "name",
      label: "Form name",
      detail: `"${before.name}" → "${after.name}"`,
    });
  }

  if ((before.description ?? "").trim() !== (after.description ?? "").trim()) {
    items.push({
      kind: "changed",
      section: "form",
      key: "description",
      label: "Description",
      detail: "Text updated",
    });
  }

  if ((before.status ?? "") !== (after.status ?? "")) {
    items.push({
      kind: "changed",
      section: "form",
      key: "status",
      label: "Status",
      detail: `${before.status ?? "—"} → ${after.status ?? "—"}`,
    });
  }

  const beforeFields = new Map(before.fields.map((f) => [f.name, f]));
  const afterFields = new Map(after.fields.map((f) => [f.name, f]));

  for (const [name, field] of afterFields) {
    if (!beforeFields.has(name)) {
      items.push({
        kind: "added",
        section: "field",
        key: name,
        label: field.label || name,
        detail: formatEApprovalFieldTypeLabel(field.type),
      });
    }
  }

  for (const [name, field] of beforeFields) {
    if (!afterFields.has(name)) {
      items.push({
        kind: "removed",
        section: "field",
        key: name,
        label: field.label || name,
        detail: formatEApprovalFieldTypeLabel(field.type),
      });
    }
  }

  for (const [name, afterField] of afterFields) {
    const beforeField = beforeFields.get(name);
    if (!beforeField) {
      continue;
    }
    if (fieldSignature(beforeField) !== fieldSignature(afterField)) {
      items.push({
        kind: "changed",
        section: "field",
        key: name,
        label: afterField.label || name,
        detail: `${formatEApprovalFieldTypeLabel(beforeField.type)} → ${formatEApprovalFieldTypeLabel(afterField.type)}`,
      });
    }
  }

  const beforeSteps = before.steps.length;
  const afterSteps = after.steps.length;
  if (beforeSteps !== afterSteps) {
    items.push({
      kind: "changed",
      section: "workflow",
      key: "step_count",
      label: "Workflow steps",
      detail: `${beforeSteps} → ${afterSteps}`,
    });
  }

  const maxSteps = Math.max(before.steps.length, after.steps.length);
  for (let i = 0; i < maxSteps; i++) {
    const b = before.steps[i];
    const a = after.steps[i];
    if (!b && a) {
      items.push({ kind: "added", section: "workflow", key: `step-${i}`, label: `Step ${i + 1}`, detail: a.type });
      continue;
    }
    if (b && !a) {
      items.push({ kind: "removed", section: "workflow", key: `step-${i}`, label: `Step ${i + 1}`, detail: b.type });
      continue;
    }
    if (b && a && stepSignature(b, i) !== stepSignature(a, i)) {
      items.push({
        kind: "changed",
        section: "workflow",
        key: `step-${i}`,
        label: `Step ${i + 1}`,
        detail: `${b.type} → ${a.type}`,
      });
    }
  }

  return items;
}
