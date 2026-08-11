import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";

export type EApprovalApproverFieldOption = { id: string; label: string };

/** Steps that would be persisted and can activate approvers on submit. */
export function getValidEApprovalWorkflowSteps(
  steps: EApprovalWorkflowStepInput[],
): EApprovalWorkflowStepInput[] {
  return steps.filter((step) => {
    if (step.type === "manager") {
      return true;
    }
    if (step.type === "field") {
      return Boolean(step.approverId?.trim());
    }
    if (step.type === "user_list") {
      return Boolean(step.approverId?.trim());
    }
    if (step.type === "field_map") {
      const sourceField = (step.source_field ?? step.approverId ?? "").trim();
      const mappings = step.mappings ?? {};
      const hasMapping = Object.values(mappings).some((value) => Boolean(String(value).trim()));
      const hasDefault = Boolean(step.default_approver_id?.trim());

      return Boolean(sourceField) && (hasMapping || hasDefault);
    }
    return Boolean(step.approverId?.trim());
  });
}

export function hasValidEApprovalWorkflowSteps(steps: EApprovalWorkflowStepInput[]): boolean {
  return getValidEApprovalWorkflowSteps(steps).length > 0;
}

export function getApproverFieldOptions(fields: EApprovalFormFieldInput[]): EApprovalApproverFieldOption[] {
  return fields
    .filter((field) => field.type === "approver" && field.name.trim() !== "")
    .map((field) => ({
      id: field.name,
      label: field.label?.trim() || field.name,
    }));
}

export function getApproverListFieldOptions(fields: EApprovalFormFieldInput[]): EApprovalApproverFieldOption[] {
  return fields
    .filter((field) => field.type === "approver_list" && field.name.trim() !== "")
    .map((field) => ({
      id: field.name,
      label: field.label?.trim() || field.name,
    }));
}

export function getUsedApproverFieldIds(
  steps: EApprovalWorkflowStepInput[],
  excludeIndex?: number,
): Set<string> {
  const used = new Set<string>();

  steps.forEach((step, index) => {
    if (excludeIndex !== undefined && index === excludeIndex) {
      return;
    }
    if (step.type === "field" && step.approverId?.trim()) {
      used.add(step.approverId.trim());
    }
    if (step.type === "user_list" && step.approverId?.trim()) {
      used.add(step.approverId.trim());
    }
  });

  return used;
}

export function pickNextApproverFieldId(
  fields: EApprovalFormFieldInput[],
  steps: EApprovalWorkflowStepInput[],
  excludeIndex?: number,
): string {
  const options = getApproverFieldOptions(fields);
  if (options.length === 0) {
    return "";
  }

  const used = getUsedApproverFieldIds(steps, excludeIndex);
  const nextUnused = options.find((option) => !used.has(option.id));

  return nextUnused?.id ?? options[0].id;
}

export function pickNextApproverListFieldId(
  fields: EApprovalFormFieldInput[],
  steps: EApprovalWorkflowStepInput[],
  excludeIndex?: number,
): string {
  const options = getApproverListFieldOptions(fields);
  if (options.length === 0) {
    return "";
  }

  const used = getUsedApproverFieldIds(steps, excludeIndex);
  const nextUnused = options.find((option) => !used.has(option.id));

  return nextUnused?.id ?? options[0].id;
}

export function suggestNextWorkflowStep(
  fields: EApprovalFormFieldInput[],
  steps: EApprovalWorkflowStepInput[],
  approverOptions: { id: string; label: string }[],
): EApprovalWorkflowStepInput {
  const approverFieldId = pickNextApproverFieldId(fields, steps);

  if (approverFieldId) {
    return {
      type: "field",
      approverId: approverFieldId,
      step_order: steps.length + 1,
    };
  }

  return {
    type: "user",
    approverId: approverOptions[0]?.id ?? "",
    step_order: steps.length + 1,
  };
}

export function workflowStepStatusLabel(steps: EApprovalWorkflowStepInput[]): string {
  const total = steps.length;
  if (total === 0) {
    return "";
  }

  const ready = getValidEApprovalWorkflowSteps(steps).length;
  const incomplete = total - ready;

  if (incomplete > 0) {
    return incomplete === 1
      ? `${total} workflow step${total === 1 ? "" : "s"} — 1 needs approver assignment`
      : `${total} workflow steps — ${incomplete} need approver assignment`;
  }

  return `${total} workflow step${total === 1 ? "" : "s"} configured`;
}
