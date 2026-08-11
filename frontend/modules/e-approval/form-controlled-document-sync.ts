import { parseControlledDocumentSync } from "@/modules/e-approval/controlled-document-sync";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type ControlledDocumentSyncFieldKey =
  | "title"
  | "document_type"
  | "department"
  | "revision_number"
  | "effective_date"
  | "change_summary";

export type ControlledDocumentSyncEditorSettings = {
  enabled: boolean;
  autoRevision: boolean;
  documentCodeField: string;
  attachmentField: string;
  fieldMap: Record<ControlledDocumentSyncFieldKey, string>;
};

export const CONTROLLED_DOCUMENT_FIELD_DEFINITIONS: ReadonlyArray<{
  key: ControlledDocumentSyncFieldKey;
  label: string;
  types: readonly string[];
  preferNames: readonly string[];
}> = [
  { key: "title", label: "Title", types: ["text"], preferNames: ["title"] },
  {
    key: "document_type",
    label: "Document type",
    types: ["select", "radio", "text"],
    preferNames: ["document_type", "documentType"],
  },
  {
    key: "department",
    label: "Department",
    types: ["select", "radio", "text"],
    preferNames: ["department"],
  },
  {
    key: "revision_number",
    label: "Revision",
    types: ["number", "text"],
    preferNames: ["revision_number", "revision"],
  },
  {
    key: "effective_date",
    label: "Effective date",
    types: ["date"],
    preferNames: ["effective_date", "effectiveDate"],
  },
  {
    key: "change_summary",
    label: "Change summary",
    types: ["textarea", "text"],
    preferNames: ["change_summary", "changeSummary"],
  },
];

export const DEFAULT_CONTROLLED_DOCUMENT_SYNC: ControlledDocumentSyncEditorSettings = {
  enabled: false,
  autoRevision: true,
  documentCodeField: "document_code",
  attachmentField: "attachments",
  fieldMap: {
    title: "title",
    document_type: "document_type",
    department: "department",
    revision_number: "revision_number",
    effective_date: "effective_date",
    change_summary: "change_summary",
  },
};

const DOCUMENT_CODE_TYPES = new Set(["text"]);
const ATTACHMENT_TYPES = new Set(["file"]);

export function controlledDocumentSyncSettingsFromMetadata(
  metadata: unknown,
): ControlledDocumentSyncEditorSettings {
  const parsed = parseControlledDocumentSync(metadata);
  if (!parsed) {
    return { ...DEFAULT_CONTROLLED_DOCUMENT_SYNC };
  }

  return {
    enabled: true,
    autoRevision: parsed.autoRevision,
    documentCodeField: parsed.documentCodeField,
    attachmentField: parsed.attachmentField,
    fieldMap: {
      title: parsed.fieldMap.title ?? DEFAULT_CONTROLLED_DOCUMENT_SYNC.fieldMap.title,
      document_type: parsed.fieldMap.document_type ?? DEFAULT_CONTROLLED_DOCUMENT_SYNC.fieldMap.document_type,
      department: parsed.fieldMap.department ?? DEFAULT_CONTROLLED_DOCUMENT_SYNC.fieldMap.department,
      revision_number:
        parsed.fieldMap.revision_number ?? DEFAULT_CONTROLLED_DOCUMENT_SYNC.fieldMap.revision_number,
      effective_date:
        parsed.fieldMap.effective_date ?? DEFAULT_CONTROLLED_DOCUMENT_SYNC.fieldMap.effective_date,
      change_summary:
        parsed.fieldMap.change_summary ?? DEFAULT_CONTROLLED_DOCUMENT_SYNC.fieldMap.change_summary,
    },
  };
}

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

export function mergeControlledDocumentSyncIntoMetadata(
  metadata: Record<string, unknown>,
  settings: ControlledDocumentSyncEditorSettings,
): Record<string, unknown> {
  const next = { ...metadata };

  if (!settings.enabled) {
    delete next.controlledDocumentSync;
    delete next.controlled_document_sync;
    return next;
  }

  const existingSync = asRecord(metadata.controlledDocumentSync) ?? asRecord(metadata.controlled_document_sync);
  const existingAccessPolicy = existingSync?.accessPolicy ?? existingSync?.access_policy;

  const syncPayload: Record<string, unknown> = {
    enabled: true,
    autoRevision: settings.autoRevision,
    documentCodeField: settings.documentCodeField.trim() || "document_code",
    attachmentField: settings.attachmentField.trim() || "attachments",
    fieldMap: { ...settings.fieldMap },
    composeUi: {
      hideSectionProgress: true,
      hideRegistryPicker: true,
    },
  };

  if (existingAccessPolicy !== undefined && existingAccessPolicy !== null) {
    syncPayload.accessPolicy = existingAccessPolicy;
  }

  next.controlledDocumentSync = syncPayload;

  return next;
}

export function fieldsForControlledDocumentSlot(
  fields: EApprovalFormFieldInput[],
  slot: ControlledDocumentSyncFieldKey | "document_code" | "attachments",
): EApprovalFormFieldInput[] {
  if (slot === "document_code") {
    return fields.filter((field) => DOCUMENT_CODE_TYPES.has(field.type) && field.name.trim() !== "");
  }

  if (slot === "attachments") {
    return fields.filter((field) => ATTACHMENT_TYPES.has(field.type) && field.name.trim() !== "");
  }

  const definition = CONTROLLED_DOCUMENT_FIELD_DEFINITIONS.find((item) => item.key === slot);
  if (!definition) {
    return [];
  }

  const allowed = new Set(definition.types);
  return fields.filter((field) => allowed.has(field.type) && field.name.trim() !== "");
}

export function suggestControlledDocumentSyncSettings(
  fields: EApprovalFormFieldInput[],
  current: ControlledDocumentSyncEditorSettings = DEFAULT_CONTROLLED_DOCUMENT_SYNC,
): ControlledDocumentSyncEditorSettings {
  const fieldMap = { ...current.fieldMap };

  for (const definition of CONTROLLED_DOCUMENT_FIELD_DEFINITIONS) {
    const candidates = fieldsForControlledDocumentSlot(fields, definition.key);
    const preferred = definition.preferNames
      .map((name) => candidates.find((field) => field.name === name))
      .find(Boolean);
    const fallback = candidates[0];
    const match = preferred ?? fallback;
    if (match) {
      fieldMap[definition.key] = match.name;
    }
  }

  const documentCodeCandidates = fieldsForControlledDocumentSlot(fields, "document_code");
  const documentCodeField =
    documentCodeCandidates.find((field) => field.name === "document_code")?.name ??
    documentCodeCandidates[0]?.name ??
    current.documentCodeField;

  const attachmentCandidates = fieldsForControlledDocumentSlot(fields, "attachments");
  const attachmentField =
    attachmentCandidates.find((field) => field.name === "attachments")?.name ??
    attachmentCandidates[0]?.name ??
    current.attachmentField;

  return {
    ...current,
    enabled: true,
    fieldMap,
    documentCodeField,
    attachmentField,
  };
}

export function controlledDocumentSyncReadiness(
  settings: ControlledDocumentSyncEditorSettings,
  fields: EApprovalFormFieldInput[],
): { ready: boolean; warnings: string[] } {
  if (!settings.enabled) {
    return { ready: true, warnings: [] };
  }

  const warnings: string[] = [];
  const fieldNames = new Set(fields.map((field) => field.name));

  for (const definition of CONTROLLED_DOCUMENT_FIELD_DEFINITIONS) {
    const mapped = settings.fieldMap[definition.key]?.trim();
    if (!mapped || !fieldNames.has(mapped)) {
      warnings.push(`Map "${definition.label}" to a form field on the Design tab.`);
    }
  }

  const codeField = settings.documentCodeField.trim();
  if (codeField && !fieldNames.has(codeField)) {
    warnings.push('Add an "Existing document code" text field for revision submissions.');
  }

  return { ready: warnings.length === 0, warnings };
}
