import type { EApprovalApprovalPolicyConfig } from "@/modules/e-approval/approval-policy-types";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { fieldDefaultValue, parseFieldValidation } from "@/modules/e-approval/field-validation";
import { parseSubmissionAmount } from "@/modules/e-approval/parent-submission-link";

export type ApproverOption = { id: string; label: string };

function documentFamilyFromMetadata(metadata: Record<string, unknown> | null | undefined): string | null {
  const family = String(metadata?.form_family ?? "").trim();

  return family !== "" ? family : null;
}

function defaultAmountField(documentFamily: string | null): string {
  if (documentFamily === "purchase_requisition") {
    return "estimated_total";
  }
  if (documentFamily === "purchase_order") {
    return "total_amount";
  }

  return "amount";
}

function numericAmount(values: Record<string, string>, fieldName: string): number | null {
  const raw = values[fieldName];
  if (raw !== undefined && raw.trim() !== "") {
    return parseSubmissionAmount(raw);
  }

  for (const fallback of ["grand_total", "total_amount", "estimated_total", "requested_amount"]) {
    if (fallback === fieldName) {
      continue;
    }
    const candidate = values[fallback];
    if (candidate !== undefined && candidate.trim() !== "") {
      const parsed = parseSubmissionAmount(candidate);
      if (parsed !== null) {
        return parsed;
      }
    }
  }

  return null;
}

function ruleMatches(
  rule: Record<string, unknown>,
  context: {
    document_family: string | null;
    department: string | null;
    urgency: string | null;
    category: string | null;
    amount: number | null;
  },
): boolean {
  const family = String(rule.document_family ?? "").trim();
  if (family !== "" && family !== (context.document_family ?? "")) {
    return false;
  }

  for (const dimension of ["department", "category", "urgency"] as const) {
    const expected = rule[dimension];
    if (expected === null || expected === undefined || String(expected).trim() === "") {
      continue;
    }
    if (String(expected) !== String(context[dimension] ?? "")) {
      return false;
    }
  }

  const amount = context.amount;
  if (amount === null) {
    return rule.amount_min == null && rule.amount_max == null;
  }

  if (rule.amount_min != null && rule.amount_min !== "" && amount < Number(rule.amount_min)) {
    return false;
  }

  if (rule.amount_max != null && rule.amount_max !== "" && amount > Number(rule.amount_max)) {
    return false;
  }

  return true;
}

export function matchApprovalPolicyProfile(
  policy: EApprovalApprovalPolicyConfig | null | undefined,
  metadata: Record<string, unknown> | null | undefined,
  values: Record<string, string>,
): string | null {
  if (!policy) {
    return null;
  }

  const documentFamily = documentFamilyFromMetadata(metadata);
  if (!documentFamily) {
    return null;
  }

  const amountField = defaultAmountField(documentFamily);
  const context = {
    document_family: documentFamily,
    department: (values.department ?? "").trim() || null,
    urgency: (values.urgency ?? "").trim() || null,
    category: null,
    amount: numericAmount(values, amountField),
  };

  const rules = [...(policy.rules ?? [])].sort(
    (left, right) => Number(right.priority ?? 0) - Number(left.priority ?? 0),
  );

  for (const rule of rules) {
    if (ruleMatches(rule as unknown as Record<string, unknown>, context)) {
      const profileKey = String(rule.workflow_profile ?? "").trim();
      if (profileKey !== "") {
        return profileKey;
      }
    }
  }

  const fallback = String(policy.default_profiles?.[documentFamily] ?? "").trim();

  return fallback !== "" ? fallback : null;
}

export function workflowApproverFieldNames(
  policy: EApprovalApprovalPolicyConfig | null | undefined,
  profileKey: string | null,
): string[] {
  if (!policy || !profileKey) {
    return [];
  }

  const profile = policy.workflow_profiles?.[profileKey];
  if (!profile) {
    return [];
  }

  const names = new Set<string>();
  for (const step of profile.steps ?? []) {
    if (step.type === "field" && step.approverId?.trim()) {
      names.add(step.approverId.trim());
    }
  }

  return [...names];
}

export function resolveApproverFieldValue(
  value: string,
  options: ApproverOption[],
): string {
  const trimmed = value.trim();
  if (trimmed === "") {
    return "";
  }

  if (options.some((option) => option.id === trimmed)) {
    return trimmed;
  }

  const byExactLabel = options.find((option) => option.label === trimmed);
  if (byExactLabel) {
    return byExactLabel.id;
  }

  const lowered = trimmed.toLowerCase();
  const byCaseInsensitiveLabel = options.find((option) => option.label.toLowerCase() === lowered);
  if (byCaseInsensitiveLabel) {
    return byCaseInsensitiveLabel.id;
  }

  const emailMatch = trimmed.match(/[\w.+-]+@[\w.-]+\.\w+/);
  if (emailMatch) {
    const email = emailMatch[0].toLowerCase();
    const byEmail = options.find((option) => option.label.toLowerCase().includes(email));
    if (byEmail) {
      return byEmail.id;
    }
  }

  return trimmed;
}

export function normalizeApproverFieldValues(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
  options: ApproverOption[],
): Record<string, string> {
  let changed = false;
  const next = { ...values };

  for (const field of fields) {
    if (field.type !== "approver") {
      continue;
    }

    const current = next[field.name] ?? "";
    const resolved = resolveApproverFieldValue(current, options);
    if (resolved !== current) {
      next[field.name] = resolved;
      changed = true;
    }
  }

  return changed ? next : values;
}

/** Drop approver picks that no longer exist in the assignable user list. */
export function sanitizeApproverFieldValues(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
  options: ApproverOption[],
): Record<string, string> {
  let changed = false;
  const next = { ...values };

  for (const field of fields) {
    if (field.type !== "approver") {
      continue;
    }

    const current = (next[field.name] ?? "").trim();
    if (current === "") {
      continue;
    }

    const resolved = resolveApproverFieldValue(current, options);
    const valid = resolved !== "" && options.some((option) => option.id === resolved);
    if (!valid) {
      next[field.name] = "";
      changed = true;
      continue;
    }

    if (resolved !== current) {
      next[field.name] = resolved;
      changed = true;
    }
  }

  return changed ? next : values;
}

export function reconcileApproverFieldValues(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
  options: ApproverOption[],
): Record<string, string> {
  if (options.length === 0) {
    return values;
  }

  return applyApproverFieldDefaults(
    fields,
    sanitizeApproverFieldValues(fields, normalizeApproverFieldValues(fields, values, options), options),
    options,
  );
}

export function applyApproverFieldDefaults(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
  options: ApproverOption[],
): Record<string, string> {
  let changed = false;
  const next = { ...values };

  for (const field of fields) {
    if (field.type !== "approver") {
      continue;
    }

    const current = (next[field.name] ?? "").trim();
    if (current !== "") {
      continue;
    }

    const defaultValue = fieldDefaultValue(field).trim();
    if (defaultValue === "") {
      continue;
    }

    const resolved = resolveApproverFieldValue(defaultValue, options);
    if (resolved !== "" && options.some((option) => option.id === resolved)) {
      next[field.name] = resolved;
      changed = true;
    }
  }

  return changed ? next : values;
}

export function effectiveWorkflowSource(
  metadata: Record<string, unknown> | null | undefined,
): "form" | "policy" {
  if (metadata?.use_approval_policy !== true) {
    return "form";
  }

  const explicit = metadata?.workflow_source;
  if (explicit === "form" || explicit === "policy") {
    return explicit;
  }

  const effective = metadata?.effective_workflow_source;
  if (effective === "form" || effective === "policy") {
    return effective;
  }

  return "policy";
}

export function requiredApproverFieldNamesForSubmit(
  fields: EApprovalFormFieldInput[],
  metadata: Record<string, unknown> | null | undefined,
  values: Record<string, string>,
  policy: EApprovalApprovalPolicyConfig | null | undefined,
): Set<string> | null {
  const usesPolicy = metadata?.use_approval_policy === true;
  if (!usesPolicy) {
    return null;
  }

  if (effectiveWorkflowSource(metadata) === "form") {
    return null;
  }

  const profileKey = matchApprovalPolicyProfile(policy, metadata, values);
  const workflowFields = new Set(workflowApproverFieldNames(policy, profileKey));

  if (workflowFields.size > 0) {
    return workflowFields;
  }

  return new Set(
    fields
      .filter((field) => field.type === "approver" && parseFieldValidation(field).required)
      .map((field) => field.name),
  );
}

export function isApproverSelectionValid(value: string, options: ApproverOption[]): boolean {
  const resolved = resolveApproverFieldValue(value, options);

  return resolved !== "" && options.some((option) => option.id === resolved);
}
