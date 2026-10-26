import { formatEApprovalFieldTypeLabel } from "@/modules/e-approval/field-types";
import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";

export type EApprovalFormSnapshot = {
  name: string;
  description?: string | null;
  status?: string;
  fields: EApprovalFormFieldInput[];
  steps: EApprovalWorkflowStepInput[];
  metadata?: Record<string, unknown> | null;
};

export type RevisionDiffItem = {
  kind: "added" | "removed" | "changed";
  section: "form" | "field" | "workflow" | "email";
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
  const metadata = asRecord(payload.metadata_json);

  return {
    name: String(payload.name ?? ""),
    description: typeof payload.description === "string" ? payload.description : null,
    status: typeof payload.status === "string" ? payload.status : undefined,
    fields,
    steps,
    metadata: metadata ? stripHistoryMetadata(metadata) : null,
  };
}

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

function stripHistoryMetadata(metadata: Record<string, unknown>): Record<string, unknown> {
  const next = { ...metadata };
  delete next.revisions;
  delete next.effective_workflow_source;

  return next;
}

function stable(value: unknown): string {
  return JSON.stringify(sortValue(value));
}

function sortValue(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map(sortValue);
  }
  const record = asRecord(value);
  if (!record) {
    return value ?? null;
  }

  return Object.keys(record)
    .sort()
    .reduce<Record<string, unknown>>((acc, key) => {
      acc[key] = sortValue(record[key]);
      return acc;
    }, {});
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
  return stable({
    order: step.step_order ?? index + 1,
    type: step.type,
    approverId: step.approverId ?? "",
    label: step.label ?? "",
    mail: step.mail ?? null,
    when: step.when ?? null,
    when_logic: step.when_logic ?? null,
    optional: step.optional === true,
    parallel_mode: step.parallel_mode ?? null,
    parallel_quorum: step.parallel_quorum ?? null,
    fallback_approver_id: step.fallback_approver_id ?? "",
    duty: typeof step.condition?.duty === "string" ? step.condition.duty : null,
    confirm_settlement: step.confirm_settlement === true,
  });
}

function stepChangeDetail(before: EApprovalWorkflowStepInput, after: EApprovalWorkflowStepInput): string {
  const parts: string[] = [];
  if (before.type !== after.type) {
    parts.push(`${before.type} → ${after.type}`);
  }
  if ((before.approverId ?? "") !== (after.approverId ?? "")) {
    parts.push("Approver changed");
  }
  if ((before.label ?? "") !== (after.label ?? "")) {
    parts.push(`Status label "${before.label || "—"}" → "${after.label || "—"}"`);
  }
  if (stable(before.mail ?? null) !== stable(after.mail ?? null)) {
    parts.push("Email changed");
  }
  if (stable(before.when ?? null) !== stable(after.when ?? null) || before.when_logic !== after.when_logic) {
    parts.push("Conditions changed");
  }
  if (before.optional !== after.optional) {
    parts.push(after.optional ? "Marked optional" : "No longer optional");
  }

  return parts.join(" · ") || "Step settings changed";
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
        label: `Step ${a.step_order ?? i + 1}`,
        detail: stepChangeDetail(b, a),
      });
    }
  }

  items.push(...emailAndSettingsDiff(before.metadata ?? null, after.metadata ?? null));

  return items;
}

function emailAndSettingsDiff(
  beforeMeta: Record<string, unknown> | null,
  afterMeta: Record<string, unknown> | null,
): RevisionDiffItem[] {
  const beforeClean = stripHistoryMetadata(beforeMeta ?? {});
  const afterClean = stripHistoryMetadata(afterMeta ?? {});
  const items: RevisionDiffItem[] = [];
  const beforeNotes = asRecord(beforeClean.notifications);
  const afterNotes = asRecord(afterClean.notifications);
  delete beforeClean.notifications;
  delete afterClean.notifications;

  diffNotifications(items, beforeNotes, afterNotes);

  if (stable(beforeClean) !== stable(afterClean)) {
    items.push({
      kind: "changed",
      section: "form",
      key: "settings",
      label: "Form settings",
      detail: "Workflow, access, or numbering settings changed",
    });
  }

  return items;
}

function diffNotifications(
  items: RevisionDiffItem[],
  beforeNotes: Record<string, unknown> | null,
  afterNotes: Record<string, unknown> | null,
): void {
  const beforeStatuses = asRecord(beforeNotes?.statuses) ?? {};
  const afterStatuses = asRecord(afterNotes?.statuses) ?? {};
  const statusKeys = new Set([...Object.keys(beforeStatuses), ...Object.keys(afterStatuses)]);
  for (const key of statusKeys) {
    const left = String(beforeStatuses[key] ?? "");
    const right = String(afterStatuses[key] ?? "");
    if (left === right) {
      continue;
    }
    items.push({
      kind: "changed",
      section: "email",
      key: `status-${key}`,
      label: `Status name: ${key}`,
      detail: `"${left || "default"}" → "${right || "default"}"`,
    });
  }

  const beforeEvents = asRecord(beforeNotes?.events) ?? {};
  const afterEvents = asRecord(afterNotes?.events) ?? {};
  const eventKeys = new Set([...Object.keys(beforeEvents), ...Object.keys(afterEvents)]);
  for (const key of eventKeys) {
    if (stable(beforeEvents[key] ?? null) === stable(afterEvents[key] ?? null)) {
      continue;
    }
    items.push({
      kind: "changed",
      section: "email",
      key: `event-${key}`,
      label: `Email: ${key.replaceAll("_", " ")}`,
      detail: "Subject, body, buttons, or on/off changed",
    });
  }
}
