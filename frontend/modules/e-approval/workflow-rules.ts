import { parseSelectChoices } from "@/modules/e-approval/field-options";
import type {
  EApprovalFormFieldInput,
  EApprovalWorkflowCondition,
  EApprovalWorkflowConditionOperator,
  EApprovalWorkflowStepInput,
} from "@/modules/e-approval/types";
import { getValidEApprovalWorkflowSteps } from "@/modules/e-approval/workflow-steps";

export type EApprovalWorkflowMode = "simple" | "rules";

export type { EApprovalWorkflowCondition, EApprovalWorkflowConditionOperator };

export type EApprovalWorkflowRule = {
  id: string;
  label: string;
  priority: number;
  conditions: EApprovalWorkflowCondition[];
  steps: EApprovalWorkflowStepInput[];
};

export type EApprovalWorkflowConfig = {
  mode: EApprovalWorkflowMode;
  rules: EApprovalWorkflowRule[];
  defaultSteps: EApprovalWorkflowStepInput[];
};

export const E_APPROVAL_WORKFLOW_CONDITION_OPERATORS: {
  value: EApprovalWorkflowConditionOperator;
  label: string;
  needsValue: boolean;
}[] = [
  { value: "equals", label: "equals", needsValue: true },
  { value: "not_equals", label: "does not equal", needsValue: true },
  { value: "contains", label: "contains", needsValue: true },
  { value: "gt", label: "greater than", needsValue: true },
  { value: "gte", label: "greater than or equal", needsValue: true },
  { value: "lt", label: "less than", needsValue: true },
  { value: "lte", label: "less than or equal", needsValue: true },
  { value: "is_empty", label: "is empty", needsValue: false },
  { value: "is_not_empty", label: "is not empty", needsValue: false },
  { value: "in", label: "is one of (comma-separated)", needsValue: true },
];

export function parseWorkflowConfig(metadata: Record<string, unknown>): EApprovalWorkflowConfig {
  const mode = metadata.workflow_mode === "rules" ? "rules" : "simple";
  const rules = parseWorkflowRules(metadata.workflow_rules);
  const defaultSteps = parseWorkflowDefaultSteps(metadata.workflow_default_steps);

  return { mode, rules, defaultSteps };
}

export function patchWorkflowConfig(
  metadata: Record<string, unknown>,
  patch: Partial<EApprovalWorkflowConfig>,
): Record<string, unknown> {
  const next = { ...metadata };
  const mode = patch.mode ?? (metadata.workflow_mode === "rules" ? "rules" : "simple");

  if (mode === "rules") {
    next.workflow_mode = "rules";
    if (patch.rules !== undefined) {
      next.workflow_rules = patch.rules;
    }
    if (patch.defaultSteps !== undefined) {
      next.workflow_default_steps = patch.defaultSteps;
    }
  } else {
    delete next.workflow_mode;
    delete next.workflow_rules;
    delete next.workflow_default_steps;
  }

  return next;
}

export function parseWorkflowRules(raw: unknown): EApprovalWorkflowRule[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  return raw
    .filter((item): item is Record<string, unknown> => Boolean(item) && typeof item === "object" && !Array.isArray(item))
    .map((rule, index) => ({
      id: String(rule.id ?? `rule-${index + 1}`),
      label: String(rule.label ?? `Rule ${index + 1}`),
      priority: Number(rule.priority ?? 100 - index),
      conditions: parseWorkflowConditions(rule.conditions),
      steps: parseWorkflowRuleSteps(rule.steps),
    }))
    .filter((rule) => rule.steps.length > 0)
    .sort((a, b) => b.priority - a.priority);
}

export function parseWorkflowDefaultSteps(raw: unknown): EApprovalWorkflowStepInput[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  return raw
    .filter((item): item is Record<string, unknown> => Boolean(item) && typeof item === "object" && !Array.isArray(item))
    .map((step, index) => normalizeWorkflowRuleStep(step, index));
}

function parseWorkflowConditions(raw: unknown): EApprovalWorkflowCondition[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  return raw
    .filter((item): item is Record<string, unknown> => Boolean(item) && typeof item === "object" && !Array.isArray(item))
    .map((condition) => ({
      field: String(condition.field ?? ""),
      operator: normalizeConditionOperator(condition.operator),
      value: condition.value !== undefined && condition.value !== null ? String(condition.value) : "",
    }))
    .filter((condition) => condition.field.trim() !== "");
}

function parseWorkflowRuleSteps(raw: unknown): EApprovalWorkflowStepInput[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  return raw
    .filter((item): item is Record<string, unknown> => Boolean(item) && typeof item === "object" && !Array.isArray(item))
    .map((step, index) => normalizeWorkflowRuleStep(step, index));
}

function normalizeWorkflowRuleStep(step: Record<string, unknown>, index: number): EApprovalWorkflowStepInput {
  const type = String(step.type ?? step.approver_type ?? "user");
  const base: EApprovalWorkflowStepInput = {
    id: step.id ? String(step.id) : undefined,
    type,
    approverId: step.approverId ? String(step.approverId) : step.approver_id ? String(step.approver_id) : undefined,
    step_order: Number(step.step_order ?? index + 1),
    condition: isRecord(step.condition) ? step.condition : null,
  };

  if (type === "field_map") {
    return {
      ...base,
      source_field: String(step.source_field ?? base.approverId ?? ""),
      mappings: isRecord(step.mappings)
        ? Object.fromEntries(
            Object.entries(step.mappings).map(([key, value]) => [key, String(value)]),
          )
        : {},
      default_approver_id: step.default_approver_id ? String(step.default_approver_id) : undefined,
    };
  }

  return base;
}

function normalizeConditionOperator(raw: unknown): EApprovalWorkflowConditionOperator {
  const value = String(raw ?? "equals").toLowerCase();
  const allowed = E_APPROVAL_WORKFLOW_CONDITION_OPERATORS.map((op) => op.value);

  return (allowed.includes(value as EApprovalWorkflowConditionOperator)
    ? value
    : "equals") as EApprovalWorkflowConditionOperator;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}

export function newWorkflowRuleId(): string {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return `rule-${crypto.randomUUID().slice(0, 8)}`;
  }

  return `rule-${Date.now().toString(16).slice(-8)}`;
}

export function createEmptyWorkflowRule(priority = 100): EApprovalWorkflowRule {
  return {
    id: newWorkflowRuleId(),
    label: "New rule",
    priority,
    conditions: [{ field: "", operator: "equals", value: "" }],
    steps: [{ type: "user", approverId: "", step_order: 1 }],
  };
}

export function getConditionFieldOptions(fields: EApprovalFormFieldInput[]): { id: string; label: string }[] {
  return fields
    .filter((field) => !["section", "divider", "grid", "file", "signature"].includes(field.type))
    .filter((field) => field.name.trim() !== "")
    .map((field) => ({
      id: field.name,
      label: field.label?.trim() || field.name,
    }));
}

export function getFieldMapSourceOptions(fields: EApprovalFormFieldInput[]): { id: string; label: string }[] {
  return fields
    .filter((field) => ["select", "radio", "text", "number", "currency"].includes(field.type))
    .filter((field) => field.name.trim() !== "")
    .map((field) => ({
      id: field.name,
      label: field.label?.trim() || field.name,
    }));
}

export function suggestFieldMapMappings(
  fields: EApprovalFormFieldInput[],
  sourceField: string,
): Record<string, string> {
  const field = fields.find((item) => item.name === sourceField);
  if (!field) {
    return {};
  }

  const choices = parseSelectChoices(field);
  if (choices.length === 0) {
    return {};
  }

  return Object.fromEntries(choices.map((choice) => [choice.value, ""]));
}

export function isWorkflowConditionComplete(condition: EApprovalWorkflowCondition): boolean {
  if (!condition.field.trim()) {
    return false;
  }

  const operator = E_APPROVAL_WORKFLOW_CONDITION_OPERATORS.find((item) => item.value === condition.operator);
  if (!operator?.needsValue) {
    return true;
  }

  return Boolean(condition.value?.trim());
}

export function isWorkflowRuleComplete(rule: EApprovalWorkflowRule): boolean {
  if (!rule.label.trim()) {
    return false;
  }

  if (rule.conditions.length === 0 || !rule.conditions.every(isWorkflowConditionComplete)) {
    return false;
  }

  return getValidEApprovalWorkflowSteps(rule.steps).length > 0;
}

export function hasValidWorkflowRulesConfiguration(config: EApprovalWorkflowConfig): boolean {
  if (config.mode !== "rules") {
    return false;
  }

  const completeRules = config.rules.filter(isWorkflowRuleComplete);
  if (completeRules.length > 0) {
    return true;
  }

  return getValidEApprovalWorkflowSteps(config.defaultSteps).length > 0;
}

export function workflowRulesStatusLabel(config: EApprovalWorkflowConfig): string {
  if (config.mode !== "rules") {
    return "";
  }

  const completeRules = config.rules.filter(isWorkflowRuleComplete);
  const incompleteRules = config.rules.length - completeRules.length;
  const defaultReady = getValidEApprovalWorkflowSteps(config.defaultSteps).length > 0;

  if (completeRules.length === 0 && !defaultReady) {
    return "Rules mode — add at least one complete rule or a default chain";
  }

  if (incompleteRules > 0) {
    return `${config.rules.length} rule${config.rules.length === 1 ? "" : "s"} — ${incompleteRules} incomplete`;
  }

  return `${completeRules.length} rule${completeRules.length === 1 ? "" : "s"}${defaultReady ? " + default chain" : ""}`;
}
