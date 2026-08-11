import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type EApprovalFieldListEntry = {
  field: EApprovalFormFieldInput;
  index: number;
};

/** Stable React key for a field instance (names alone are not unique in rows). */
export function fieldInstanceKey(field: EApprovalFormFieldInput, index: number): string {
  return field.id ?? `${field.name}@${index}`;
}

export type EApprovalFieldDisplayGroup = {
  /** Section field that starts this group; null for fields before the first section. */
  header: EApprovalFieldListEntry | null;
  /** Fields belonging to this group (excludes the section header row). */
  items: EApprovalFieldListEntry[];
};

/**
 * Groups fields under section headings for builder display.
 * Fields after a section belong to that section until the next section.
 */
export function buildFieldDisplayGroups(fields: EApprovalFormFieldInput[]): EApprovalFieldDisplayGroup[] {
  const groups: EApprovalFieldDisplayGroup[] = [];
  let current: EApprovalFieldDisplayGroup = { header: null, items: [] };

  const flush = () => {
    if (current.header !== null || current.items.length > 0) {
      groups.push(current);
    }
    current = { header: null, items: [] };
  };

  fields.forEach((field, index) => {
    if (field.type === "section") {
      flush();
      current = {
        header: { field, index },
        items: [],
      };
      return;
    }

    current.items.push({ field, index });
  });

  flush();

  return groups.length > 0 ? groups : [{ header: null, items: [] }];
}
