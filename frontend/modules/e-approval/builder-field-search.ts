import { formatEApprovalFieldTypeLabel } from "@/modules/e-approval/field-types";
import type { EApprovalFieldDisplayGroup } from "@/modules/e-approval/form-field-groups";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type BuilderFieldSearchEntry = {
  index: number;
  groupIndex: number;
  label: string;
  name: string;
  type: string;
  typeLabel: string;
  haystack: string;
};

function pushEntry(
  entries: BuilderFieldSearchEntry[],
  field: EApprovalFormFieldInput,
  index: number,
  groupIndex: number,
): void {
  const label = field.label?.trim() || field.name;
  const name = field.name?.trim() || "";
  const typeLabel = formatEApprovalFieldTypeLabel(field.type);

  entries.push({
    index,
    groupIndex,
    label,
    name,
    type: field.type,
    typeLabel,
    haystack: `${label} ${name} ${typeLabel} ${field.type}`.toLowerCase(),
  });
}

export function buildBuilderFieldSearchIndex(
  fields: EApprovalFormFieldInput[],
  groups: EApprovalFieldDisplayGroup[],
): BuilderFieldSearchEntry[] {
  const entries: BuilderFieldSearchEntry[] = [];

  groups.forEach((group, groupIndex) => {
    if (group.header) {
      pushEntry(entries, group.header.field, group.header.index, groupIndex);
    }

    for (const item of group.items) {
      pushEntry(entries, item.field, item.index, groupIndex);
    }
  });

  if (entries.length === 0) {
    fields.forEach((field, index) => {
      pushEntry(entries, field, index, 0);
    });
  }

  return entries;
}

export function filterBuilderFieldSearch(
  entries: BuilderFieldSearchEntry[],
  query: string,
  limit = 40,
): BuilderFieldSearchEntry[] {
  const trimmed = query.trim().toLowerCase();
  if (!trimmed) {
    return [];
  }

  const tokens = trimmed.split(/\s+/).filter(Boolean);
  const matches: BuilderFieldSearchEntry[] = [];

  for (const entry of entries) {
    if (tokens.every((token) => entry.haystack.includes(token))) {
      matches.push(entry);
      if (matches.length >= limit) {
        break;
      }
    }
  }

  return matches;
}

export function builderCanvasFieldAnchorId(fieldIndex: number): string {
  return `ea-builder-field-${fieldIndex}`;
}
