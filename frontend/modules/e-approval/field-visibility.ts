import { isComposeStructuralFieldType } from "@/modules/e-approval/form-compose-structural";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type EApprovalVisibilityMode = "show_when" | "hide_when";

export type EApprovalVisibilityOperator = "equals" | "not_equals" | "filled" | "empty" | "contains";

export type EApprovalFieldVisibilityRule = {
  mode: EApprovalVisibilityMode;
  field: string;
  operator: EApprovalVisibilityOperator;
  value?: string;
};

function normalizeOptions(options: EApprovalFormFieldInput["options"]): Record<string, unknown> {
  if (options && typeof options === "object" && !Array.isArray(options)) {
    return { ...(options as Record<string, unknown>) };
  }

  return {};
}

export function parseFieldVisibility(field: EApprovalFormFieldInput): EApprovalFieldVisibilityRule | null {
  const opts = normalizeOptions(field.options);
  const raw = opts.visibility;
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) {
    return null;
  }

  const v = raw as Record<string, unknown>;
  const mode = v.mode === "hide_when" ? "hide_when" : v.mode === "show_when" ? "show_when" : null;
  const whenField = typeof v.field === "string" ? v.field.trim() : "";
  const operatorRaw = v.operator;
  const operator: EApprovalVisibilityOperator | null =
    operatorRaw === "equals" ||
    operatorRaw === "not_equals" ||
    operatorRaw === "filled" ||
    operatorRaw === "empty" ||
    operatorRaw === "contains"
      ? operatorRaw
      : null;

  if (!mode || !whenField || !operator) {
    return null;
  }

  const value = typeof v.value === "string" ? v.value : undefined;

  if ((operator === "equals" || operator === "not_equals" || operator === "contains") && (value === undefined || value.trim() === "")) {
    return null;
  }

  return { mode, field: whenField, operator, value };
}

export function patchFieldVisibility(
  field: EApprovalFormFieldInput,
  rule: EApprovalFieldVisibilityRule | null,
): Record<string, unknown> {
  const opts = normalizeOptions(field.options);

  if (!rule || !rule.field.trim()) {
    const { visibility: _removed, ...rest } = opts;
    return { ...rest, visibility: undefined };
  }

  if (
    (rule.operator === "equals" || rule.operator === "not_equals" || rule.operator === "contains") &&
    (rule.value === undefined || rule.value.trim() === "")
  ) {
    const { visibility: _removed, ...rest } = opts;
    return { ...rest, visibility: undefined };
  }

  const visibility: Record<string, unknown> = {
    mode: rule.mode,
    field: rule.field.trim(),
    operator: rule.operator,
  };
  if (rule.value !== undefined && rule.value !== "") {
    visibility.value = rule.value;
  }

  return { ...opts, visibility };
}

export function fieldSupportsVisibilityRules(type: string): boolean {
  return !isComposeStructuralFieldType(type);
}

function conditionMatches(
  operator: EApprovalVisibilityOperator,
  controlValue: string,
  expected?: string,
): boolean {
  const value = controlValue.trim();
  const compare = (expected ?? "").trim();

  switch (operator) {
    case "empty":
      return value === "";
    case "filled":
      return value !== "" && value !== "false";
    case "equals":
      return value.toLowerCase() === compare.toLowerCase();
    case "not_equals":
      return value.toLowerCase() !== compare.toLowerCase();
    case "contains":
      return compare !== "" && value.toLowerCase().includes(compare.toLowerCase());
    default:
      return false;
  }
}

export function evaluateFieldVisibility(
  rule: EApprovalFieldVisibilityRule,
  values: Record<string, string>,
): boolean {
  const controlValue = values[rule.field] ?? "";
  const matches = conditionMatches(rule.operator, controlValue, rule.value);

  return rule.mode === "show_when" ? matches : !matches;
}

export function isFieldVisible(field: EApprovalFormFieldInput, values: Record<string, string>): boolean {
  const rule = parseFieldVisibility(field);
  if (!rule) {
    return true;
  }

  return evaluateFieldVisibility(rule, values);
}

export function visibleFormFields(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): EApprovalFormFieldInput[] {
  return fields.filter((field) => isFieldVisible(field, values));
}

export const E_APPROVAL_VISIBILITY_OPERATORS: { value: EApprovalVisibilityOperator; label: string }[] = [
  { value: "equals", label: "Equals" },
  { value: "not_equals", label: "Does not equal" },
  { value: "contains", label: "Contains" },
  { value: "filled", label: "Is filled" },
  { value: "empty", label: "Is empty" },
];
