import { isFieldVisible } from "@/modules/e-approval/field-visibility";
import { buildFieldDisplayGroups } from "@/modules/e-approval/form-field-groups";
import { gridHasContent, parseGridColumns, parseGridValue } from "@/modules/e-approval/field-options";
import { parseFieldValidation } from "@/modules/e-approval/field-validation";
import { isComposeFillableFieldType } from "@/modules/e-approval/form-compose-structural";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type EApprovalFormSectionProgress = {
  id: string;
  groupIndex: number;
  label: string;
  completed: number;
  total: number;
};

function isFieldComplete(field: EApprovalFormFieldInput, values: Record<string, string>): boolean {
  const value = (values[field.name] ?? "").trim();
  const rules = parseFieldValidation(field);

  if (field.type === "grid") {
    if (!rules.required) {
      return value !== "";
    }
    const columns = parseGridColumns(field);
    const grid = parseGridValue(value, columns.length);
    return gridHasContent(grid);
  }

  if (!rules.required) {
    return value !== "";
  }

  return value !== "";
}

function countVisibleFillable(fields: EApprovalFormFieldInput[], values: Record<string, string>): number {
  return fields.filter((f) => isComposeFillableFieldType(f.type) && isFieldVisible(f, values)).length;
}

export function buildFormSectionProgress(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): EApprovalFormSectionProgress[] {
  if (countVisibleFillable(fields, values) < 8) {
    return [];
  }

  const groups = buildFieldDisplayGroups(fields);
  const hasSectionHeaders = groups.some((g) => g.header !== null);
  if (!hasSectionHeaders && countVisibleFillable(fields, values) < 14) {
    return [];
  }

  return groups
    .map((group, groupIndex) => {
      const inGroup: EApprovalFormFieldInput[] = [];
      if (group.header && isComposeFillableFieldType(group.header.field.type)) {
        inGroup.push(group.header.field);
      }
      for (const item of group.items) {
        inGroup.push(item.field);
      }

      const fillable = inGroup.filter((f) => isComposeFillableFieldType(f.type) && isFieldVisible(f, values));
      if (fillable.length === 0) {
        return null;
      }

      const required = fillable.filter((f) => {
        const rules = parseFieldValidation(f);
        return rules.required || f.type === "grid";
      });
      const track = required.length > 0 ? required : fillable;
      const completed = track.filter((f) => isFieldComplete(f, values)).length;

      const label =
        group.header?.field.label?.trim() ||
        (groupIndex === 0 && groups.length === 1 ? "Form" : `Section ${groupIndex + 1}`);

      return {
        id: `ea-section-${groupIndex}`,
        groupIndex,
        label,
        completed,
        total: track.length,
      };
    })
    .filter((s): s is EApprovalFormSectionProgress => s !== null);
}

export function shouldShowFormSectionProgress(sections: EApprovalFormSectionProgress[]): boolean {
  return sections.length >= 2 || (sections.length === 1 && sections[0]!.total >= 10);
}
