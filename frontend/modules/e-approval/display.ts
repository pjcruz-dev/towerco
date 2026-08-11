import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type EApprovalSubmissionFieldValue = {
  value: string | null;
  display_value?: string | null;
  display_subtitle?: string | null;
  field_type?: string | null;
  field_id?: string;
};

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function isUuidLike(value: string | null | undefined): boolean {
  if (!value) {
    return false;
  }

  return UUID_PATTERN.test(value.trim());
}

/** Human-readable value for submission detail, lists, and exports. Never surfaces raw UUIDs. */
export function formatEApprovalFieldDisplayValue(
  field: EApprovalSubmissionFieldValue,
  empty = "—",
): string {
  const display = field.display_value?.trim();
  if (display) {
    return display;
  }

  const raw = field.value?.trim();
  if (!raw) {
    return empty;
  }

  if (isUuidLike(raw)) {
    return field.field_type === "approver" ? "Former user" : empty;
  }

  return raw;
}

/** Approver user IDs selected on more than one approver field (duplicate picks). */
export function getDuplicateApproverIds(values: EApprovalSubmissionFieldValue[]): Set<string> {
  const counts = new Map<string, number>();

  for (const row of values) {
    if (row.field_type !== "approver") {
      continue;
    }

    const id = row.value?.trim();
    if (!id) {
      continue;
    }

    counts.set(id, (counts.get(id) ?? 0) + 1);
  }

  return new Set(
    [...counts.entries()].filter(([, count]) => count > 1).map(([id]) => id),
  );
}

export function shouldShowApproverDuplicateSubtitle(
  field: EApprovalSubmissionFieldValue,
  duplicateApproverIds: Set<string>,
): boolean {
  if (field.field_type !== "approver" || duplicateApproverIds.size === 0) {
    return false;
  }

  const id = field.value?.trim();
  return Boolean(id && duplicateApproverIds.has(id) && field.display_subtitle?.trim());
}

/**
 * Block submit when the same user is picked on multiple approver fields.
 */
export function validateDuplicateApproverFields(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): string | null {
  const seen = new Map<string, string>();

  for (const field of fields) {
    if (field.type !== "approver") {
      continue;
    }

    const id = (values[field.name] ?? "").trim();
    if (!id) {
      continue;
    }

    const previousLabel = seen.get(id);
    if (previousLabel) {
      return `The same approver cannot be assigned to both “${previousLabel}” and “${field.label}”. Choose a different person for each step.`;
    }

    seen.set(id, field.label);
  }

  return null;
}
