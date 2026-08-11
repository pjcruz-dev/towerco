import type { EApprovalFieldDisplayGroup } from "@/modules/e-approval/form-field-groups";
import { isComposeFillableFieldType } from "@/modules/e-approval/form-compose-structural";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export const BUILDER_FIELD_SEARCH_MIN_FIELDS = 50;
export const BUILDER_LARGE_FORM_MIN_FIELDS = 100;

export function countBuilderFillableFields(fields: EApprovalFormFieldInput[]): number {
  return fields.filter((field) => isComposeFillableFieldType(field.type)).length;
}

export function shouldShowBuilderFieldSearch(fields: EApprovalFormFieldInput[]): boolean {
  return countBuilderFillableFields(fields) >= BUILDER_FIELD_SEARCH_MIN_FIELDS;
}

export function isLargeBuilderForm(fields: EApprovalFormFieldInput[]): boolean {
  return countBuilderFillableFields(fields) >= BUILDER_LARGE_FORM_MIN_FIELDS;
}

export function shouldForceBuilderCanvasOutline(
  fields: EApprovalFormFieldInput[],
  groups: EApprovalFieldDisplayGroup[],
): boolean {
  if (isLargeBuilderForm(fields)) {
    return true;
  }

  const fieldCount = groups.reduce(
    (sum, group) => sum + group.items.filter((entry) => isComposeFillableFieldType(entry.field.type)).length,
    0,
  );
  const sectionCount = groups.filter((group) => group.header !== null).length;

  return fieldCount >= 8 || sectionCount >= 2;
}

export function findDisplayGroupIndexForField(
  groups: EApprovalFieldDisplayGroup[],
  fieldIndex: number,
): number {
  return groups.findIndex(
    (group) =>
      group.header?.index === fieldIndex || group.items.some((item) => item.index === fieldIndex),
  );
}
