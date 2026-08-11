import {
  isComposeFillableFieldType,
  resolveEffectiveStepSource,
  type FormComposeStepSource,
} from "@/modules/e-approval/form-compose-structural";
import {
  buildFieldDisplayGroups,
  type EApprovalFieldDisplayGroup,
} from "@/modules/e-approval/form-field-groups";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type FormComposeStep = {
  id: string;
  stepIndex: number;
  label: string;
  sectionFieldIndex: number | null;
  fieldIndices: number[];
  fields: EApprovalFormFieldInput[];
};

function buildSectionComposeSteps(
  fields: EApprovalFormFieldInput[],
  options?: { includeEmptySteps?: boolean },
): FormComposeStep[] {
  const includeEmptySteps = options?.includeEmptySteps === true;
  const groups = buildFieldDisplayGroups(fields);

  return groups
    .map((group, stepIndex) => {
      const fieldIndices: number[] = [];
      if (group.header) {
        fieldIndices.push(group.header.index);
      }
      for (const item of group.items) {
        fieldIndices.push(item.index);
      }

      const stepFields = fieldIndices.map((index) => fields[index]!);
      const fillableCount = stepFields.filter((field) => isComposeFillableFieldType(field.type)).length;
      if (fillableCount === 0 && !includeEmptySteps) {
        return null;
      }
      // Builder: keep section-only steps so a newly dropped heading becomes Step N.
      if (fillableCount === 0 && includeEmptySteps && group.header === null) {
        return null;
      }

      const label =
        group.header?.field.label?.trim() ||
        (stepIndex === 0 && groups.length === 1 ? "Form" : `Step ${stepIndex + 1}`);

      return {
        id: `ea-compose-step-${stepIndex}`,
        stepIndex,
        label,
        sectionFieldIndex: group.header?.index ?? null,
        fieldIndices,
        fields: stepFields,
      };
    })
    .filter((step): step is FormComposeStep => step !== null);
}

function defaultPageBreakStepLabel(stepIndex: number): string {
  return `Step ${stepIndex + 1}`;
}

function pageBreakStepLabel(field: EApprovalFormFieldInput, stepIndex: number): string {
  const label = field.label?.trim();
  if (!label || label === "—" || label === "Page break") {
    return defaultPageBreakStepLabel(stepIndex);
  }

  return label;
}

function buildPageBreakComposeSteps(
  fields: EApprovalFormFieldInput[],
  options?: { includeEmptySteps?: boolean },
): FormComposeStep[] {
  const includeEmptySteps = options?.includeEmptySteps === true;
  const steps: FormComposeStep[] = [];
  let currentIndices: number[] = [];
  let pendingNextLabel: string | null = null;

  const flushStep = (labelOverride?: string | null) => {
    const stepFields = currentIndices.map((index) => fields[index]!);
    const fillableCount = stepFields.filter((field) => isComposeFillableFieldType(field.type)).length;
    if (fillableCount === 0 && !includeEmptySteps) {
      currentIndices = [];
      return;
    }
    if (currentIndices.length === 0 && !includeEmptySteps) {
      return;
    }

    const stepIndex = steps.length;
    const label = labelOverride?.trim() || pendingNextLabel || defaultPageBreakStepLabel(stepIndex);

    steps.push({
      id: `ea-compose-step-${stepIndex}`,
      stepIndex,
      label,
      sectionFieldIndex: null,
      fieldIndices: [...currentIndices],
      fields: stepFields,
    });

    currentIndices = [];
    pendingNextLabel = null;
  };

  for (let index = 0; index < fields.length; index += 1) {
    const field = fields[index]!;
    if (field.type === "page_break") {
      flushStep();
      pendingNextLabel = pageBreakStepLabel(field, steps.length);
      continue;
    }

    currentIndices.push(index);
  }

  flushStep(pendingNextLabel);

  return steps;
}

export type BuildFormComposeStepsOptions = {
  /**
   * When true, keep section/page-break steps that have no fillable fields yet.
   * Used by the visual builder so dropping a Section heading creates a visible Step N.
   * Requestor compose should leave this false.
   */
  includeEmptySteps?: boolean;
};

export function buildFormComposeSteps(
  fields: EApprovalFormFieldInput[],
  stepSource: FormComposeStepSource = "sections",
  options?: BuildFormComposeStepsOptions,
): FormComposeStep[] {
  const effective = resolveEffectiveStepSource(fields, stepSource);

  if (effective === "page_breaks") {
    return buildPageBreakComposeSteps(fields, options);
  }

  return buildSectionComposeSteps(fields, options);
}

/** Field indices visible on the builder canvas for one compose step (includes trailing page breaks). */
export function buildBuilderStepVisibleIndices(
  step: FormComposeStep,
  fields: EApprovalFormFieldInput[],
): Set<number> {
  const indices = new Set(step.fieldIndices);

  if (step.fieldIndices.length === 0) {
    return indices;
  }

  const lastIndex = Math.max(...step.fieldIndices);
  const trailing = fields[lastIndex + 1];
  if (trailing?.type === "page_break") {
    indices.add(lastIndex + 1);
  }

  return indices;
}

export function filterDisplayGroupsForStepIndices(
  groups: EApprovalFieldDisplayGroup[],
  visibleIndices: Set<number>,
): EApprovalFieldDisplayGroup[] {
  return groups
    .map((group) => ({
      header: group.header && visibleIndices.has(group.header.index) ? group.header : null,
      items: group.items.filter((item) => visibleIndices.has(item.index)),
    }))
    .filter((group) => group.header !== null || group.items.length > 0);
}

/**
 * Builder canvas groups for one compose step.
 * - Section-based steps: use `sectionFieldIndex` as the group header.
 * - Page-break steps: still show any Section heading fields (they were previously hidden).
 *   If the first field in the step is a section, promote it to the header for clearer UX.
 */
export function buildDisplayGroupsForComposeStep(
  step: FormComposeStep,
  fields: EApprovalFormFieldInput[],
): EApprovalFieldDisplayGroup[] {
  let headerIndex = step.sectionFieldIndex;
  if (
    (headerIndex === null || fields[headerIndex]?.type !== "section") &&
    step.fieldIndices.length > 0
  ) {
    const firstIndex = step.fieldIndices[0]!;
    if (fields[firstIndex]?.type === "section") {
      headerIndex = firstIndex;
    } else {
      headerIndex = null;
    }
  }

  const headerField =
    headerIndex !== null && fields[headerIndex]?.type === "section"
      ? { field: fields[headerIndex]!, index: headerIndex }
      : null;

  const items = step.fieldIndices
    .filter((index) => index !== headerField?.index)
    .map((index) => {
      const field = fields[index];
      if (!field) {
        return null;
      }
      return { field, index };
    })
    .filter((entry): entry is { field: EApprovalFormFieldInput; index: number } => entry !== null);

  if (headerField === null && items.length === 0) {
    return [];
  }

  return [{ header: headerField, items }];
}

export function findComposeStepIndexForFieldName(
  steps: FormComposeStep[],
  fieldName: string,
): number {
  return steps.findIndex((step) => step.fields.some((field) => field.name === fieldName));
}

export function filterFieldErrorsForComposeStep(
  fieldErrors: Record<string, string>,
  step: FormComposeStep,
): Record<string, string> {
  const names = new Set(step.fields.map((field) => field.name));
  const next: Record<string, string> = {};

  for (const [key, message] of Object.entries(fieldErrors)) {
    if (key === "_form" || names.has(key)) {
      next[key] = message;
    }
  }

  return next;
}
