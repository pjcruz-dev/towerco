import type { ControlledDocumentSyncMeta } from "@/modules/e-approval/controlled-document-sync";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type ControlledDocumentRequestMode = "new" | "revision";

export function resolveControlledDocumentRequestMode(
  sync: ControlledDocumentSyncMeta | null,
  values: Record<string, string>,
): ControlledDocumentRequestMode {
  if (!sync) {
    return "new";
  }

  const code = (values[sync.documentCodeField] ?? "").trim();
  return code !== "" ? "revision" : "new";
}

export function controlledDocumentFieldsHiddenInNewMode(
  sync: ControlledDocumentSyncMeta,
  fields: EApprovalFormFieldInput[],
): Set<string> {
  const hidden = new Set<string>([
    sync.revisionFieldName,
    sync.fieldMap.change_summary ?? "change_summary",
    sync.fieldMap.next_review_date ?? "next_review_date",
    "previous_revision",
    "revision",
    "current_revision",
    "reason_for_change",
    "details_of_change",
    "section_revision",
    "section_change",
    "next_review_date",
    "review_date",
  ]);

  const codeFieldName = sync.documentCodeField.trim();
  const codeField = fields.find((field) => field.name === codeFieldName);
  const codeLabel = codeField?.label?.toLowerCase() ?? "";
  const isRevisionOnlyCodeField =
    codeFieldName === "document_code" ||
    codeFieldName.includes("existing") ||
    codeLabel.includes("existing document") ||
    codeLabel.includes("existing code");

  if (isRevisionOnlyCodeField) {
    hidden.add(codeFieldName);
  }

  return hidden;
}

function fieldAssignedOnPublish(field: EApprovalFormFieldInput): boolean {
  if (field.name === "dcf_number") {
    return true;
  }

  const options = field.options;
  if (!options || typeof options !== "object" || Array.isArray(options)) {
    return false;
  }

  return (options as Record<string, unknown>).assigned_on_publish === true;
}

export function filterFieldsForControlledDocumentMode(
  fields: EApprovalFormFieldInput[],
  sync: ControlledDocumentSyncMeta | null,
  mode: ControlledDocumentRequestMode,
): EApprovalFormFieldInput[] {
  const requestFields = fields.filter((field) => !fieldAssignedOnPublish(field));

  if (!sync) {
    return requestFields;
  }

  if (mode === "new") {
    const hidden = controlledDocumentFieldsHiddenInNewMode(sync, requestFields);
    const withoutHiddenFields = requestFields.filter((field) => !hidden.has(field.name));

    return removeEmptySectionHeadings(withoutHiddenFields);
  }

  // Revision mode: hide the document code field (chosen via the registry alert/banner above)
  // and next_review_date (managed post-approval by document controllers, not on the form).
  const revisionHidden = new Set([
    sync.documentCodeField,
    sync.fieldMap.next_review_date ?? "next_review_date",
    "next_review_date",
    "review_date",
  ]);

  return removeEmptySectionHeadings(requestFields.filter((field) => !revisionHidden.has(field.name)));
}

/** Drop section headings that have no remaining fields in their group. */
export function removeEmptySectionHeadings(fields: EApprovalFormFieldInput[]): EApprovalFormFieldInput[] {
  const result: EApprovalFormFieldInput[] = [];
  let index = 0;

  while (index < fields.length) {
    const field = fields[index];
    if (field.type !== "section") {
      result.push(field);
      index += 1;
      continue;
    }

    let end = index + 1;
    while (end < fields.length && fields[end]?.type !== "section") {
      end += 1;
    }

    const sectionChildren = fields.slice(index + 1, end);
    if (sectionChildren.length > 0) {
      result.push(field, ...sectionChildren);
    }

    index = end;
  }

  return result;
}

export function controlledDocumentDraftLooksLikeRevision(
  sync: ControlledDocumentSyncMeta,
  values: Record<string, string>,
): boolean {
  return (values[sync.documentCodeField] ?? "").trim() !== "";
}

/** JSON null is stored as the four characters "null". That is an empty answer. */
export function blankStoredControlledDocumentValue(value: string | null | undefined): string {
  const raw = value ?? "";
  return raw.trim().toLowerCase() === "null" ? "" : raw;
}

/**
 * A rejected new request already has a document code. That code is not a register
 * revision. Revision sections apply only when the request revised a published document.
 */
export function controlledDocumentSubmissionIsRegisterRevision(
  sync: ControlledDocumentSyncMeta,
  values: Record<string, string>,
): boolean {
  const previous = blankStoredControlledDocumentValue(values.previous_revision).trim();
  if (previous !== "") {
    return true;
  }

  const revision = blankStoredControlledDocumentValue(values[sync.revisionFieldName]).trim();
  const revisionNumber = Number(revision);

  return revision !== "" && Number.isFinite(revisionNumber) && revisionNumber > 0;
}

export function clearControlledDocumentRevisionValues(
  sync: ControlledDocumentSyncMeta,
  values: Record<string, string>,
): Record<string, string> {
  return {
    ...values,
    [sync.documentCodeField]: "",
    [sync.revisionFieldName]: "",
    previous_revision: "",
    reason_for_change: "",
    ...(sync.fieldMap.change_summary ? { [sync.fieldMap.change_summary]: "" } : {}),
  };
}

export function controlledDocumentRequestModeLabel(mode: ControlledDocumentRequestMode): string {
  return mode === "revision" ? "Revision of existing document" : "New controlled document";
}

export function validateControlledDocumentRequest(
  sync: ControlledDocumentSyncMeta | null,
  mode: ControlledDocumentRequestMode,
  values: Record<string, string>,
): { fieldName: string; message: string } | null {
  if (!sync || mode !== "revision") {
    return null;
  }

  const code = (values[sync.documentCodeField] ?? "").trim();
  if (code === "") {
    return {
      fieldName: sync.documentCodeField,
      message: "Enter the existing document code you are revising (e.g. ATC-QMS-P-001).",
    };
  }

  return null;
}
