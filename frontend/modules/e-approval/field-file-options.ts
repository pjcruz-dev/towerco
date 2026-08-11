import { parseFieldValidation } from "@/modules/e-approval/field-validation";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export const E_APPROVAL_ALLOWED_FILE_TYPES = [
  "jpeg",
  "png",
  "pdf",
  "doc",
  "docx",
  "xls",
  "xlsx",
  "ppt",
  "pptx",
] as const;

export type EApprovalAllowedFileType = (typeof E_APPROVAL_ALLOWED_FILE_TYPES)[number];

export const ALLOWED_FILE_TYPE_LABELS: Record<EApprovalAllowedFileType, string> = {
  jpeg: "JPEG",
  png: "PNG",
  pdf: "PDF",
  doc: "Word (.doc)",
  docx: "Word (.docx)",
  xls: "Excel (.xls)",
  xlsx: "Excel (.xlsx)",
  ppt: "PowerPoint (.ppt)",
  pptx: "PowerPoint (.pptx)",
};

/** Default types for general E-Approval uploads. */
const DEFAULT_ALLOWED: EApprovalAllowedFileType[] = ["jpeg", "png", "pdf"];

/** ISO / controlled-document forms typically need Office formats. */
export const CONTROLLED_DOCUMENT_DEFAULT_FILE_TYPES: EApprovalAllowedFileType[] = [
  "pdf",
  "doc",
  "docx",
  "xlsx",
  "pptx",
];

const MAX_FILES_CAP = 20;
const DEFAULT_MAX_FILES = 5;
/** Aligns with toweros.tenant_files.max_size_kb default (25600 KB). */
export const DEFAULT_MAX_FILE_SIZE_MB = 25;
export const PLATFORM_MAX_FILE_SIZE_MB = 25;

function readValidationRecord(field: EApprovalFormFieldInput): Record<string, unknown> {
  const raw = field.validation;
  return raw && typeof raw === "object" && !Array.isArray(raw) ? (raw as Record<string, unknown>) : {};
}

function normalizeMaxFileSizeMb(raw: unknown): number | null {
  if (!isNumericLike(raw)) {
    return null;
  }

  const value = Number(raw);
  if (!Number.isFinite(value) || value <= 0) {
    return null;
  }

  return Math.min(PLATFORM_MAX_FILE_SIZE_MB, Math.max(0.1, value));
}

function normalizeMinFileSizeKb(raw: unknown): number | null {
  if (!isNumericLike(raw)) {
    return null;
  }

  const value = Number(raw);
  if (!Number.isFinite(value) || value <= 0) {
    return null;
  }

  return Math.max(1, Math.floor(value));
}

function isNumericLike(raw: unknown): boolean {
  return typeof raw === "number" || (typeof raw === "string" && raw.trim() !== "");
}

function normalizeAllowedTypes(raw: unknown): EApprovalAllowedFileType[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  const types = raw
    .map((item) => {
      const normalized = String(item ?? "").trim().toLowerCase();
      // Form imports / builders often store "jpg"; canonical type is "jpeg".
      return normalized === "jpg" ? "jpeg" : normalized;
    })
    .filter((item): item is EApprovalAllowedFileType =>
      (E_APPROVAL_ALLOWED_FILE_TYPES as readonly string[]).includes(item),
    );

  return types.length > 0 ? [...new Set(types)] : [];
}

function defaultAllowedTypesForField(field: EApprovalFormFieldInput): EApprovalAllowedFileType[] {
  const validation = readValidationRecord(field);
  const explicit = normalizeAllowedTypes(validation.allowedFileTypes);
  if (explicit.length > 0) {
    return explicit;
  }

  if (field.name === "attachments" || validation.maxFileSizeMb != null) {
    return [...CONTROLLED_DOCUMENT_DEFAULT_FILE_TYPES];
  }

  return [...DEFAULT_ALLOWED];
}

export function parseFileFieldOptions(field: EApprovalFormFieldInput): EApprovalFileFieldOptions {
  const validation = readValidationRecord(field);
  const maxRaw = validation.maxFiles ?? validation.max_files;
  const maxNumeric =
    typeof maxRaw === "number"
      ? maxRaw
      : typeof maxRaw === "string" && maxRaw.trim() !== ""
        ? Number(maxRaw)
        : NaN;
  const maxFiles = Number.isFinite(maxNumeric)
    ? Math.min(MAX_FILES_CAP, Math.max(1, Math.floor(maxNumeric)))
    : DEFAULT_MAX_FILES;

  return {
    allowedFileTypes: defaultAllowedTypesForField(field),
    maxFiles,
    maxFileSizeMb: normalizeMaxFileSizeMb(validation.maxFileSizeMb),
    minFileSizeKb: normalizeMinFileSizeKb(validation.minFileSizeKb),
  };
}

export type EApprovalFileFieldOptions = {
  allowedFileTypes: EApprovalAllowedFileType[];
  maxFiles: number;
  maxFileSizeMb: number | null;
  minFileSizeKb: number | null;
};

export function patchFileFieldOptions(
  field: EApprovalFormFieldInput,
  patch: Partial<EApprovalFileFieldOptions>,
): Record<string, unknown> | null {
  const current = parseFileFieldOptions(field);
  const base = parseFieldValidation(field);
  const next: EApprovalFileFieldOptions = {
    allowedFileTypes: patch.allowedFileTypes ?? current.allowedFileTypes,
    maxFiles: patch.maxFiles ?? current.maxFiles,
    maxFileSizeMb: patch.maxFileSizeMb !== undefined ? patch.maxFileSizeMb : current.maxFileSizeMb,
    minFileSizeKb: patch.minFileSizeKb !== undefined ? patch.minFileSizeKb : current.minFileSizeKb,
  };

  const validation: Record<string, unknown> = {};
  if (base.required) {
    validation.required = true;
  }
  if (base.placeholder) {
    validation.placeholder = base.placeholder;
  }
  if (base.max_length) {
    validation.max_length = base.max_length;
  }
  if (base.default) {
    validation.default = base.default;
  }
  if (base.help_text) {
    validation.help_text = base.help_text;
  }

  validation.allowedFileTypes = next.allowedFileTypes;
  validation.maxFiles = next.maxFiles;

  if (next.maxFileSizeMb != null) {
    validation.maxFileSizeMb = next.maxFileSizeMb;
  } else {
    delete validation.maxFileSizeMb;
  }

  if (next.minFileSizeKb != null) {
    validation.minFileSizeKb = next.minFileSizeKb;
  } else {
    delete validation.minFileSizeKb;
  }

  return validation;
}

export function fileAcceptAttribute(types: EApprovalAllowedFileType[]): string {
  const parts: string[] = [];
  if (types.includes("jpeg")) {
    parts.push("image/jpeg", ".jpg", ".jpeg");
  }
  if (types.includes("png")) {
    parts.push("image/png", ".png");
  }
  if (types.includes("pdf")) {
    parts.push("application/pdf", ".pdf");
  }
  if (types.includes("doc")) {
    parts.push("application/msword", ".doc");
  }
  if (types.includes("docx")) {
    parts.push("application/vnd.openxmlformats-officedocument.wordprocessingml.document", ".docx");
  }
  if (types.includes("xls")) {
    parts.push("application/vnd.ms-excel", ".xls");
  }
  if (types.includes("xlsx")) {
    parts.push("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", ".xlsx");
  }
  if (types.includes("ppt")) {
    parts.push("application/vnd.ms-powerpoint", ".ppt");
  }
  if (types.includes("pptx")) {
    parts.push("application/vnd.openxmlformats-officedocument.presentationml.presentation", ".pptx");
  }
  return parts.join(",");
}

export function fileMatchesAllowedTypes(file: File, types: EApprovalAllowedFileType[]): boolean {
  const ext = file.name.split(".").pop()?.toLowerCase() ?? "";
  const mime = file.type.toLowerCase();

  for (const type of types) {
    if (type === "jpeg" && (mime === "image/jpeg" || ext === "jpg" || ext === "jpeg")) {
      return true;
    }
    if (type === "png" && (mime === "image/png" || ext === "png")) {
      return true;
    }
    if (type === "pdf" && (mime === "application/pdf" || ext === "pdf")) {
      return true;
    }
    if (type === "doc" && (mime === "application/msword" || ext === "doc")) {
      return true;
    }
    if (
      type === "docx" &&
      (mime === "application/vnd.openxmlformats-officedocument.wordprocessingml.document" || ext === "docx")
    ) {
      return true;
    }
    if (type === "xls" && (mime === "application/vnd.ms-excel" || ext === "xls")) {
      return true;
    }
    if (
      type === "xlsx" &&
      (mime === "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" || ext === "xlsx")
    ) {
      return true;
    }
    if (type === "ppt" && (mime === "application/vnd.ms-powerpoint" || ext === "ppt")) {
      return true;
    }
    if (
      type === "pptx" &&
      (mime === "application/vnd.openxmlformats-officedocument.presentationml.presentation" || ext === "pptx")
    ) {
      return true;
    }
  }

  return false;
}

export function formatFileFieldValue(files: File[]): string {
  return files.map((file) => file.name).join(", ");
}

export function validateSelectedFiles(
  field: EApprovalFormFieldInput,
  files: File[],
): string | null {
  const { allowedFileTypes, maxFiles, maxFileSizeMb, minFileSizeKb } = parseFileFieldOptions(field);
  const label = field.label?.trim() || field.name;

  if (files.length > maxFiles) {
    return `${label}: at most ${maxFiles} file(s) allowed.`;
  }

  for (const file of files) {
    if (!fileMatchesAllowedTypes(file, allowedFileTypes)) {
      const allowed = allowedFileTypes.map((type) => ALLOWED_FILE_TYPE_LABELS[type]).join(", ");
      return `${label}: "${file.name}" is not allowed. Use ${allowed}.`;
    }

    if (maxFileSizeMb != null && file.size > maxFileSizeMb * 1024 * 1024) {
      return `${label}: "${file.name}" exceeds the ${maxFileSizeMb} MB limit.`;
    }

    if (minFileSizeKb != null && file.size < minFileSizeKb * 1024) {
      return `${label}: "${file.name}" must be at least ${minFileSizeKb} KB.`;
    }
  }

  return null;
}

export function fileFieldAllowsMultiple(field: EApprovalFormFieldInput): boolean {
  return parseFileFieldOptions(field).maxFiles > 1;
}
