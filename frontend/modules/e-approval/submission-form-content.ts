import {
  buildChecklistMatrixReviewTable,
  buildGridReviewTable,
  type ComposeReviewTable,
} from "@/modules/e-approval/form-compose-review";
import { formatCurrencyGrouping } from "@/lib/format-currency-input";
import { isComposeStructuralFieldType } from "@/modules/e-approval/form-compose-structural";
import type { EApprovalFormFieldInput, EApprovalSubmissionDetail } from "@/modules/e-approval/types";
import type { EApprovalSubmissionFieldValue } from "@/modules/e-approval/display";

export type SubmissionFormFieldSnapshot = NonNullable<EApprovalSubmissionDetail["form_fields"]>[number];
export type SubmissionFormValueRow = EApprovalSubmissionDetail["values"][number];
export type SubmissionFormAttachment = EApprovalSubmissionDetail["attachments"][number];

export type SubmissionFormContentItem = {
  key: string;
  value: SubmissionFormValueRow;
  formField: EApprovalFormFieldInput | null;
  table: ComposeReviewTable | null;
  fullWidth: boolean;
};

export type SubmissionFormContentGroup = {
  id: string;
  title: string | null;
  items: SubmissionFormContentItem[];
};

const HIDDEN_VALUE_TYPES = new Set([
  "section",
  "divider",
  "page_break",
  "instruction",
  "approver",
  "signature",
]);

export function toFormFieldInput(snapshot: SubmissionFormFieldSnapshot): EApprovalFormFieldInput {
  return {
    id: snapshot.id,
    type: snapshot.type ?? "text",
    name: snapshot.name ?? snapshot.id,
    label: snapshot.label ?? snapshot.name ?? snapshot.id,
    semantic_type: snapshot.semantic_type ?? null,
    validation: snapshot.validation ?? {},
    options: snapshot.options ?? {},
    step_order: 1,
  };
}

export function shouldHideSubmissionAttachmentFieldValue(
  value: Pick<SubmissionFormValueRow, "field_name" | "field_type" | "label" | "value">,
  attachments: Pick<SubmissionFormAttachment, "field_name" | "file_name">[],
): boolean {
  const type = (value.field_type ?? "").trim().toLowerCase();
  if (type === "file" || type === "camera") {
    if (attachments.length === 0) {
      return false;
    }
    if (attachments.some((a) => a.field_name && a.field_name === value.field_name)) {
      return true;
    }
    if (value.value && attachments.some((a) => a.file_name === value.value)) {
      return true;
    }
    // File/camera fields still belong in the attachments panel even without a field_name match.
    return true;
  }

  if (attachments.length === 0) {
    return false;
  }
  if (attachments.some((a) => a.field_name && a.field_name === value.field_name)) {
    return true;
  }
  if (value.value && attachments.some((a) => a.file_name === value.value)) {
    return true;
  }
  const label = (value.label ?? value.field_name ?? "").toLowerCase();
  return label.includes("attachment");
}

export function formatSubmissionCurrencyDisplay(
  field: Pick<EApprovalSubmissionFieldValue, "field_type" | "value" | "display_value">,
  empty = "—",
): string | null {
  if ((field.field_type ?? "").trim().toLowerCase() !== "currency") {
    return null;
  }

  const raw = (field.value ?? field.display_value ?? "").trim();
  if (!raw) {
    return empty;
  }

  // Prefer formatting the stored canonical number; fall back to display string cleanup.
  const formatted = formatCurrencyGrouping(raw.replace(/,/g, ""));
  return formatted || empty;
}

function buildTableForValue(
  value: SubmissionFormValueRow,
  formField: EApprovalFormFieldInput | null,
): ComposeReviewTable | null {
  const type = (value.field_type ?? formField?.type ?? "").trim().toLowerCase();
  const raw = value.value ?? "";

  if (type === "checklist_matrix" && formField) {
    return buildChecklistMatrixReviewTable(formField, raw);
  }

  if (type === "grid" && formField) {
    return buildGridReviewTable(formField, raw);
  }

  return null;
}

function isHiddenValueType(type: string | null | undefined): boolean {
  const normalized = (type ?? "").trim().toLowerCase();
  return HIDDEN_VALUE_TYPES.has(normalized) || isComposeStructuralFieldType(normalized);
}

/**
 * Group submission values under form section headings for the Content tab.
 * Hides structural fields, approvers/signatures, and file fields covered by the attachments panel.
 */
export function buildSubmissionFormContentGroups(
  values: SubmissionFormValueRow[],
  formFields: SubmissionFormFieldSnapshot[] | undefined,
  attachments: SubmissionFormAttachment[],
): SubmissionFormContentGroup[] {
  const fields = formFields ?? [];
  const fieldByName = new Map<string, EApprovalFormFieldInput>();
  for (const snapshot of fields) {
    const name = snapshot.name?.trim();
    if (!name) {
      continue;
    }
    fieldByName.set(name, toFormFieldInput(snapshot));
  }

  const valueByName = new Map<string, SubmissionFormValueRow>();
  for (const value of values) {
    const name = value.field_name?.trim();
    if (name) {
      valueByName.set(name, value);
    }
  }

  const usedNames = new Set<string>();
  const groups: SubmissionFormContentGroup[] = [];
  let current: SubmissionFormContentGroup = { id: "group-intro", title: null, items: [] };

  const pushItem = (value: SubmissionFormValueRow, formField: EApprovalFormFieldInput | null) => {
    const type = value.field_type ?? formField?.type ?? null;
    if (isHiddenValueType(type)) {
      return;
    }
    if (shouldHideSubmissionAttachmentFieldValue(value, attachments)) {
      return;
    }

    const table = buildTableForValue(value, formField);
    current.items.push({
      key: value.field_id,
      value,
      formField,
      table,
      fullWidth:
        Boolean(table) ||
        type === "textarea" ||
        type === "checklist_matrix" ||
        type === "grid" ||
        type === "matrix" ||
        type === "size_matrix",
    });
  };

  const flush = () => {
    if (current.items.length > 0) {
      groups.push(current);
    }
    current = { id: `group-${groups.length + 1}`, title: null, items: [] };
  };

  if (fields.length > 0) {
    for (const snapshot of fields) {
      const type = (snapshot.type ?? "").trim().toLowerCase();
      const name = snapshot.name?.trim() ?? "";

      if (type === "section") {
        flush();
        current = {
          id: `section-${snapshot.id}`,
          title: (snapshot.label ?? snapshot.name ?? "Section").trim() || "Section",
          items: [],
        };
        continue;
      }

      if (!name || isHiddenValueType(type)) {
        continue;
      }

      const value = valueByName.get(name);
      if (!value) {
        continue;
      }

      usedNames.add(name);
      pushItem(value, fieldByName.get(name) ?? toFormFieldInput(snapshot));
    }
    flush();
  }

  const leftovers = values.filter((value) => {
    const name = value.field_name?.trim();
    if (!name) {
      return !isHiddenValueType(value.field_type) && !shouldHideSubmissionAttachmentFieldValue(value, attachments);
    }
    return !usedNames.has(name);
  });

  if (leftovers.length > 0) {
    const otherGroup: SubmissionFormContentGroup = {
      id: "group-other",
      title: groups.length > 0 ? "Other fields" : null,
      items: [],
    };
    current = otherGroup;
    for (const value of leftovers) {
      const name = value.field_name?.trim() ?? "";
      pushItem(value, name ? fieldByName.get(name) ?? null : null);
    }
    if (current.items.length > 0) {
      groups.push(current);
    }
  }

  return groups.filter((group) => group.items.length > 0);
}
