import type { EApprovalFieldDisplayGroup } from "@/modules/e-approval/form-field-groups";
import { isComposeFillableFieldType } from "@/modules/e-approval/form-compose-structural";
import type { FormComposeStep } from "@/modules/e-approval/form-compose-steps";
import { buildDisplayGroupsForComposeStep } from "@/modules/e-approval/form-compose-steps";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type BuilderCanvasOutlineEntry = {
  groupIndex: number;
  anchorId: string;
  label: string;
  fieldCount: number;
  hasSectionHeader: boolean;
  stepIndex: number | null;
};

export function builderCanvasSectionAnchorId(groupIndex: number): string {
  return `ea-builder-section-${groupIndex}`;
}

function countGroupFields(group: EApprovalFieldDisplayGroup): number {
  return group.items.filter((entry) => isComposeFillableFieldType(entry.field.type)).length;
}

export function buildBuilderCanvasOutline(
  groups: EApprovalFieldDisplayGroup[],
  options?: { stepped?: boolean },
): BuilderCanvasOutlineEntry[] {
  const stepped = options?.stepped === true;

  return groups
    .map((group, groupIndex) => {
      const fieldCount = countGroupFields(group);
      if (fieldCount === 0 && group.header === null) {
        return null;
      }

      const baseLabel =
        group.header?.field.label?.trim() ||
        (groupIndex === 0 && groups.length === 1 ? "Form fields" : `Section ${groupIndex + 1}`);

      const stepIndex = stepped ? groupIndex + 1 : null;
      const label = stepped ? `Step ${stepIndex} · ${baseLabel}` : baseLabel;

      return {
        groupIndex,
        anchorId: builderCanvasSectionAnchorId(groupIndex),
        label,
        fieldCount,
        hasSectionHeader: group.header !== null,
        stepIndex,
      };
    })
    .filter((entry): entry is BuilderCanvasOutlineEntry => entry !== null);
}

/** Outline entries for every compose step (so Step 2 still appears while editing Step 1). */
export function buildBuilderCanvasOutlineFromComposeSteps(
  steps: FormComposeStep[],
  fields: EApprovalFormFieldInput[],
): BuilderCanvasOutlineEntry[] {
  return steps.map((step, stepIndex) => {
    const groups = buildDisplayGroupsForComposeStep(step, fields);
    const fieldCount = groups.reduce((sum, group) => sum + countGroupFields(group), 0);
    const hasSectionHeader = groups.some((group) => group.header !== null);
    const baseLabel = step.label.trim() || `Step ${stepIndex + 1}`;

    return {
      groupIndex: stepIndex,
      // Unique per step for React list keys; canvas scroll still uses section-0 after switching steps.
      anchorId: `ea-builder-step-outline-${stepIndex}`,
      label: `Step ${stepIndex + 1} · ${baseLabel}`,
      fieldCount,
      hasSectionHeader,
      stepIndex: stepIndex + 1,
    };
  });
}

/** Show outline navigation when the canvas is long enough to benefit from it. */
export function shouldShowBuilderCanvasOutline(groups: EApprovalFieldDisplayGroup[]): boolean {
  const fieldCount = groups.reduce((sum, group) => sum + countGroupFields(group), 0);
  const sectionCount = groups.filter((group) => group.header !== null).length;

  return fieldCount >= 8 || sectionCount >= 2;
}
