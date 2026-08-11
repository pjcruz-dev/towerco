import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import {
  E_APPROVAL_WORKFLOW_CONDITION_OPERATORS,
  getConditionFieldOptions,
  parseWorkflowConfig,
  type EApprovalWorkflowCondition,
  type EApprovalWorkflowConditionOperator,
} from "@/modules/e-approval/workflow-rules";
export type { EApprovalWorkflowCondition, EApprovalWorkflowConditionOperator };

export function parseStepWhen(step: EApprovalWorkflowStepInput): EApprovalWorkflowCondition[] {
  if (Array.isArray(step.when) && step.when.length > 0) {
    return step.when.map(normalizeCondition);
  }

  const condition = step.condition;
  if (condition && Array.isArray(condition.when)) {
    return condition.when.map((entry) =>
      normalizeCondition(entry as EApprovalWorkflowCondition),
    );
  }

  return [];
}

export function parseStepWhenLogic(step: EApprovalWorkflowStepInput): "and" | "or" {
  const raw = step.when_logic ?? (step.condition?.when_logic as string | undefined);
  return String(raw ?? "").toLowerCase() === "or" ? "or" : "and";
}

export function stepRunsAlways(step: EApprovalWorkflowStepInput): boolean {
  return parseStepWhen(step).length === 0;
}

export function whenSummary(step: EApprovalWorkflowStepInput, fields: EApprovalFormFieldInput[]): string {
  const when = parseStepWhen(step);
  if (when.length === 0) {
    return "Always";
  }

  const fieldLabels = new Map(
    getConditionFieldOptions(fields).map((field) => [field.id, field.label]),
  );
  const logic = parseStepWhenLogic(step);
  const joiner = logic === "or" ? " OR " : " · ";

  return when
    .map((condition) => {
      const fieldLabel = fieldLabels.get(condition.field) ?? condition.field;
      const operator = E_APPROVAL_WORKFLOW_CONDITION_OPERATORS.find((item) => item.value === condition.operator);
      const operatorLabel = operator?.label ?? condition.operator;
      if (!operator?.needsValue) {
        return `${fieldLabel} ${operatorLabel}`;
      }

      return `${fieldLabel} ${operatorLabel} ${condition.value ?? ""}`.trim();
    })
    .join(joiner);
}

export function createEmptyCondition(fields: EApprovalFormFieldInput[]): EApprovalWorkflowCondition {
  const firstField = getConditionFieldOptions(fields)[0]?.id ?? "";

  return {
    field: firstField,
    operator: "equals",
    value: "",
  };
}

export function patchStepWhen(
  step: EApprovalWorkflowStepInput,
  when: EApprovalWorkflowCondition[],
  logic?: "and" | "or",
): EApprovalWorkflowStepInput {
  const nextWhen = when.filter((condition) => condition.field.trim() !== "");
  const nextLogic = logic ?? parseStepWhenLogic(step);
  const baseCondition = { ...(step.condition ?? {}) };

  if (nextWhen.length === 0) {
    delete baseCondition.when;
    delete baseCondition.when_logic;
    return {
      ...step,
      when: [],
      when_logic: undefined,
      condition: Object.keys(baseCondition).length > 0 ? baseCondition : null,
    };
  }

  const nextCondition: Record<string, unknown> = {
    ...baseCondition,
    when: nextWhen,
  };
  if (nextLogic === "or") {
    nextCondition.when_logic = "or";
  } else {
    delete nextCondition.when_logic;
  }

  return {
    ...step,
    when: nextWhen,
    when_logic: nextLogic === "or" ? "or" : undefined,
    condition: nextCondition,
  };
}

export function patchStepWhenLogic(
  step: EApprovalWorkflowStepInput,
  logic: "and" | "or",
): EApprovalWorkflowStepInput {
  return patchStepWhen(step, parseStepWhen(step), logic);
}

function normalizeCondition(raw: EApprovalWorkflowCondition): EApprovalWorkflowCondition {
  return {
    field: String(raw.field ?? ""),
    operator: (raw.operator ?? "equals") as EApprovalWorkflowConditionOperator,
    value: raw.value !== undefined && raw.value !== null ? String(raw.value) : "",
  };
}

export {
  canonicalizeFieldMapMappings,
  mergeFieldMapMappings,
  nextUnmappedFieldValue,
} from "@/modules/e-approval/field-map-mappings";

export function collectWorkflowPreviewFieldNames(
  fields: EApprovalFormFieldInput[],
  steps: EApprovalWorkflowStepInput[],
): string[] {
  const names = new Set<string>();

  for (const step of steps) {
    const sourceField = (step.source_field ?? "").trim();
    if (sourceField !== "") {
      names.add(sourceField);
    }

    if ((step.type === "field" || step.type === "user_list") && step.approverId?.trim()) {
      names.add(step.approverId.trim());
    }

    for (const condition of parseStepWhen(step)) {
      if (condition.field.trim() !== "") {
        names.add(condition.field.trim());
      }
    }
  }

  if (names.size > 0) {
    return [...names];
  }

  return fields
    .filter((field) => !["section", "divider", "grid", "checklist_matrix", "file", "signature"].includes(field.type))
    .map((field) => field.name)
    .filter((name) => name.trim() !== "");
}

export function migrateLegacyWorkflowOnLoad(
  metadata: Record<string, unknown>,
  steps: EApprovalWorkflowStepInput[],
): { steps: EApprovalWorkflowStepInput[]; metadata: Record<string, unknown>; migrated: boolean } {
  const config = parseWorkflowConfig(metadata);
  if (config.mode !== "rules") {
    return { steps, metadata, migrated: false };
  }

  const migratedSteps: EApprovalWorkflowStepInput[] = [];
  let order = 1;

  for (const rule of config.rules) {
    for (const step of rule.steps) {
      migratedSteps.push(
        patchStepWhen(
          { ...step, step_order: order++ },
          rule.conditions.map((condition) => ({ ...condition })),
        ),
      );
    }
  }

  for (const step of config.defaultSteps) {
    migratedSteps.push({ ...step, step_order: order++ });
  }

  const nextMetadata = { ...metadata };
  delete nextMetadata.workflow_mode;
  delete nextMetadata.workflow_rules;
  delete nextMetadata.workflow_default_steps;

  return {
    steps: migratedSteps.length > 0 ? migratedSteps : steps,
    metadata: nextMetadata,
    migrated: true,
  };
}
