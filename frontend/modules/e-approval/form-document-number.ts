import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { parseSelectChoices } from "@/modules/e-approval/field-options";

export type EApprovalFormDocumentNumberSettings = {
  ownerCode: string;
  docTypeCode: string;
  docNoCustomEnabled: boolean;
  docNoTemplate: string;
};

export type DocumentNumberTemplateToken = {
  id: string;
  label: string;
  token: string;
  kind: "builtin" | "field";
  fieldName?: string;
};

const TOKEN_ELIGIBLE_FIELD_TYPES = new Set(["text", "number", "select", "radio"]);

export const DEFAULT_FORM_DOCUMENT_NUMBER: EApprovalFormDocumentNumberSettings = {
  ownerCode: "GEN",
  docTypeCode: "F",
  docNoCustomEnabled: false,
  docNoTemplate: "",
};

export const DOCUMENT_NUMBER_TEMPLATE_PLACEHOLDER = "{subsidiary}-{department}-{documentType}-{seq:3}";

export const DOCUMENT_NUMBER_BUILTIN_TOKENS: DocumentNumberTemplateToken[] = [
  { id: "subsidiary", label: "Subsidiary", token: "{subsidiary}", kind: "builtin" },
  { id: "department", label: "Department", token: "{department}", kind: "builtin" },
  { id: "ownerCode", label: "Owner code", token: "{ownerCode}", kind: "builtin" },
  { id: "docTypeCode", label: "Document type code", token: "{docTypeCode}", kind: "builtin" },
  { id: "seq", label: "Sequence (3 digits)", token: "{seq:3}", kind: "builtin" },
];

/** Field names already covered by built-in tokens — avoid duplicate chips. */
const BUILTIN_FIELD_TOKEN_NAMES = new Set([
  "subsidiary",
  "department",
  "ownercode",
  "owner_code",
  "doctypecode",
  "doc_type_code",
]);

export const DOCUMENT_NUMBER_TEMPLATE_TOKENS = [
  "{subsidiary}",
  "{department}",
  "{documentType}",
  "{ownerCode}",
  "{docTypeCode}",
  "{seq:3}",
] as const;

export function documentNumberFieldTokens(fields: EApprovalFormFieldInput[]): DocumentNumberTemplateToken[] {
  return fields
    .filter(
      (field) =>
        TOKEN_ELIGIBLE_FIELD_TYPES.has(field.type) &&
        field.name.trim() !== "" &&
        !BUILTIN_FIELD_TOKEN_NAMES.has(field.name.trim().toLowerCase()),
    )
    .map((field) => ({
      id: `field:${field.name}`,
      label: field.label?.trim() || field.name,
      token: `{${field.name}}`,
      kind: "field" as const,
      fieldName: field.name,
    }));
}

export function templateIncludesToken(template: string, token: string): boolean {
  return template.includes(token);
}

export function toggleTemplateToken(template: string, token: string, enabled: boolean): string {
  if (!enabled) {
    return template
      .replace(new RegExp(`-?${escapeRegExp(token)}`, "g"), "")
      .replace(/--+/g, "-")
      .replace(/^-|-$/g, "")
      .trim();
  }

  if (templateIncludesToken(template, token)) {
    return template;
  }

  const trimmed = template.trim();
  if (trimmed === "") {
    return token;
  }

  return `${trimmed}-${token}`.replace(/--+/g, "-");
}

export function buildDocumentNumberPreview(
  settings: EApprovalFormDocumentNumberSettings,
  fields: EApprovalFormFieldInput[],
  options?: { sampleDepartment?: string; sampleSubsidiary?: string },
): string {
  if (!settings.docNoCustomEnabled) {
    const owner = normalizeOwnerCode(settings.ownerCode);
    const docType = normalizeDocTypeCode(settings.docTypeCode);
    return `${owner}-${docType}-00001`;
  }

  const template = settings.docNoTemplate.trim() || DOCUMENT_NUMBER_TEMPLATE_PLACEHOLDER;
  const sampleValues = sampleFieldValuesForPreview(fields);
  const sampleDepartment = options?.sampleDepartment?.trim() || sampleValues.department || "HR";
  const sampleSubsidiary =
    options?.sampleSubsidiary?.trim() || sampleValues.subsidiary || "ATC";

  return template.replace(/\{([^}]+)\}/g, (match, rawToken: string) => {
    const token = rawToken.trim();
    if (token.startsWith("seq")) {
      const padding = Number.parseInt(token.split(":")[1] ?? "3", 10);
      return String(1).padStart(Number.isFinite(padding) ? padding : 3, "0");
    }

    const normalized = token.toLowerCase();
    if (normalized === "ownercode" || normalized === "owner_code") {
      return normalizeOwnerCode(settings.ownerCode);
    }
    if (normalized === "doctypecode" || normalized === "doc_type_code") {
      return normalizeDocTypeCode(settings.docTypeCode);
    }
    if (normalized === "documenttype" || normalized === "document_type") {
      return sanitizePreviewSegment(sampleValues.document_type ?? sampleValues.documenttype ?? "X");
    }
    if (normalized === "department") {
      return sanitizePreviewSegment(sampleDepartment);
    }
    if (normalized === "subsidiary") {
      return sanitizePreviewSegment(sampleSubsidiary);
    }

    const raw = sampleValues[token] ?? sampleValues[normalized] ?? "";
    const segment = sanitizePreviewSegment(String(raw));

    return segment !== "" ? segment : "X";
  });
}

function sampleFieldValuesForPreview(fields: EApprovalFormFieldInput[]): Record<string, string> {
  const values: Record<string, string> = {};

  for (const field of fields) {
    if (!TOKEN_ELIGIBLE_FIELD_TYPES.has(field.type)) {
      continue;
    }

    if (field.type === "select" || field.type === "radio") {
      const first = parseSelectChoices(field)[0];
      values[field.name] = first?.value ?? "";
      continue;
    }

    if (field.type === "number") {
      values[field.name] = "1";
      continue;
    }

    values[field.name] = "TEXT";
  }

  return values;
}

function sanitizePreviewSegment(raw: string): string {
  const sanitized = raw.trim().toUpperCase().replace(/[^A-Z0-9]/g, "");
  return sanitized !== "" ? sanitized : "X";
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

export function normalizeOwnerCode(value: string): string {
  return value.trim().toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 30) || "GEN";
}

export function normalizeDocTypeCode(value: string): string {
  return value.trim().toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 20) || "F";
}

export function documentNumberSettingsFromFormDetail(form: {
  owner_code?: string | null;
  doc_type_code?: string | null;
  doc_no_custom_enabled?: boolean | null;
  doc_no_template?: string | null;
}): EApprovalFormDocumentNumberSettings {
  return {
    ownerCode: normalizeOwnerCode(form.owner_code ?? DEFAULT_FORM_DOCUMENT_NUMBER.ownerCode),
    docTypeCode: normalizeDocTypeCode(form.doc_type_code ?? DEFAULT_FORM_DOCUMENT_NUMBER.docTypeCode),
    docNoCustomEnabled: form.doc_no_custom_enabled === true,
    docNoTemplate: (form.doc_no_template ?? "").trim(),
  };
}

export function documentNumberSettingsToApiPayload(
  settings: EApprovalFormDocumentNumberSettings,
): Record<string, unknown> {
  const ownerCode = normalizeOwnerCode(settings.ownerCode);
  const docTypeCode = normalizeDocTypeCode(settings.docTypeCode);
  const template = settings.docNoTemplate.trim();

  return {
    owner_code: ownerCode,
    doc_type_code: docTypeCode,
    doc_no_custom_enabled: settings.docNoCustomEnabled,
    doc_no_template: settings.docNoCustomEnabled && template !== "" ? template : null,
  };
}

export function documentNumberSettingsFromImportForm(form: Record<string, unknown>): EApprovalFormDocumentNumberSettings {
  return {
    ownerCode: normalizeOwnerCode(String(form.owner_code ?? DEFAULT_FORM_DOCUMENT_NUMBER.ownerCode)),
    docTypeCode: normalizeDocTypeCode(String(form.doc_type_code ?? DEFAULT_FORM_DOCUMENT_NUMBER.docTypeCode)),
    docNoCustomEnabled: form.doc_no_custom_enabled === true,
    docNoTemplate: typeof form.doc_no_template === "string" ? form.doc_no_template.trim() : "",
  };
}

export function documentNumberSettingsToImportForm(
  settings: EApprovalFormDocumentNumberSettings,
): Record<string, unknown> {
  const ownerCode = normalizeOwnerCode(settings.ownerCode);
  const docTypeCode = normalizeDocTypeCode(settings.docTypeCode);
  const template = settings.docNoTemplate.trim();

  return {
    owner_code: ownerCode,
    doc_type_code: docTypeCode,
    doc_no_custom_enabled: settings.docNoCustomEnabled,
    doc_no_template: settings.docNoCustomEnabled && template !== "" ? template : null,
    doc_no_seq_start: null,
    doc_no_seq_start_rules: null,
  };
}
