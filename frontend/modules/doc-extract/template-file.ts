import {
  DOC_EXTRACT_FIELD_TYPES,
  DOC_EXTRACT_TABLE_COLUMN_TYPES,
} from "@/modules/doc-extract/field-types";
import type {
  DocExtractField,
  DocExtractFieldType,
  DocExtractTableColumn,
  DocExtractTemplate,
  DocExtractTemplateStatus,
} from "@/modules/doc-extract/types";

export const DOC_EXTRACT_TEMPLATE_FILE_KIND = "toweros.doc-extract.template";
export const DOC_EXTRACT_TEMPLATE_FILE_VERSION = 1;

const FIELD_TYPES = new Set(DOC_EXTRACT_FIELD_TYPES.map((entry) => entry.value));
const COLUMN_TYPES = new Set(DOC_EXTRACT_TABLE_COLUMN_TYPES.map((entry) => entry.value));

export type DocExtractTemplateFile = {
  kind: typeof DOC_EXTRACT_TEMPLATE_FILE_KIND;
  version: number;
  name: string;
  description: string | null;
  status: DocExtractTemplateStatus;
  fields: DocExtractField[];
};

export type DocExtractTemplateImport = {
  name: string;
  description: string | null;
  status: DocExtractTemplateStatus;
  fields: DocExtractField[];
};

export function buildDocExtractTemplateFile(template: DocExtractTemplate): DocExtractTemplateFile {
  return {
    kind: DOC_EXTRACT_TEMPLATE_FILE_KIND,
    version: DOC_EXTRACT_TEMPLATE_FILE_VERSION,
    name: template.name,
    description: template.description?.trim() || null,
    status: template.status === "draft" ? "draft" : "published",
    fields: template.fields.map(exportField),
  };
}

export function downloadDocExtractTemplateFile(template: DocExtractTemplate): void {
  const file = buildDocExtractTemplateFile(template);
  const blob = new Blob([`${JSON.stringify(file, null, 2)}\n`], {
    type: "application/json",
  });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `${slugifyFileName(template.name)}.template.json`;
  link.click();
  URL.revokeObjectURL(url);
}

export function parseDocExtractTemplateFile(raw: string): DocExtractTemplateImport {
  let parsed: unknown;
  try {
    parsed = JSON.parse(raw) as unknown;
  } catch {
    throw new Error("This file is not valid JSON.");
  }
  if (parsed === null || typeof parsed !== "object" || Array.isArray(parsed)) {
    throw new Error("Template file must be a JSON object.");
  }
  const record = parsed as Record<string, unknown>;
  if (record.kind !== undefined && record.kind !== DOC_EXTRACT_TEMPLATE_FILE_KIND) {
    throw new Error("This JSON file is not a DocExtract template.");
  }
  if (record.version !== undefined && record.version !== DOC_EXTRACT_TEMPLATE_FILE_VERSION) {
    throw new Error("This template file version is not supported.");
  }
  const name = readText(record.name, "Template name");
  if (name.length > 180) {
    throw new Error("Template name must be 180 characters or fewer.");
  }
  const description = readOptionalText(record.description, "Description", 2000);
  const status = readStatus(record.status);
  if (!Array.isArray(record.fields) || record.fields.length === 0) {
    throw new Error("Template file needs at least one field.");
  }
  if (record.fields.length > 40) {
    throw new Error("Template file has too many fields.");
  }
  return {
    name,
    description,
    status,
    fields: record.fields.map((field, index) => readField(field, index)),
  };
}

export function uniqueTemplateName(name: string, existingNames: string[]): string {
  const taken = new Set(existingNames.map((item) => item.trim().toLowerCase()));
  if (!taken.has(name.trim().toLowerCase())) {
    return name;
  }
  const base = `${name} (imported)`;
  if (!taken.has(base.toLowerCase())) {
    return base;
  }
  for (let index = 2; index < 100; index += 1) {
    const candidate = `${name} (imported ${index})`;
    if (!taken.has(candidate.toLowerCase())) {
      return candidate;
    }
  }
  return `${name} (imported ${Date.now()})`;
}

function exportField(field: DocExtractField): DocExtractField {
  return {
    key: field.key,
    label: field.label,
    type: field.type,
    description: field.description?.trim() || null,
    hint: field.hint?.trim() || null,
    columns: field.type === "table" ? (field.columns ?? []).map(exportColumn) : undefined,
  };
}

function exportColumn(column: DocExtractTableColumn): DocExtractTableColumn {
  return {
    key: column.key,
    label: column.label,
    type: column.type,
    description: column.description?.trim() || null,
  };
}

function readField(value: unknown, index: number): DocExtractField {
  if (value === null || typeof value !== "object" || Array.isArray(value)) {
    throw new Error(`Field ${index + 1} is not an object.`);
  }
  const field = value as Record<string, unknown>;
  const label = readText(field.label, `Field ${index + 1} label`);
  if (label.length > 180) {
    throw new Error(`Field ${index + 1} label is too long.`);
  }
  const type = readFieldType(field.type, index);
  const key = readOptionalText(field.key, `Field ${index + 1} key`, 80) ?? "";
  const columns = type === "table" ? readColumns(field.columns, index) : undefined;
  return {
    key,
    label,
    type,
    description: readOptionalText(field.description, `Field ${index + 1} description`, 500),
    hint: readOptionalText(field.hint, `Field ${index + 1} hint`, 500),
    columns,
  };
}

function readColumns(value: unknown, fieldIndex: number): DocExtractTableColumn[] {
  if (!Array.isArray(value) || value.length === 0) {
    throw new Error(`Field ${fieldIndex + 1} is a table and needs columns.`);
  }
  if (value.length > 20) {
    throw new Error(`Field ${fieldIndex + 1} has more than 20 columns.`);
  }
  return value.map((column, index) => {
    if (column === null || typeof column !== "object" || Array.isArray(column)) {
      throw new Error(`Field ${fieldIndex + 1} column ${index + 1} is not an object.`);
    }
    const record = column as Record<string, unknown>;
    const label = readText(record.label, `Field ${fieldIndex + 1} column ${index + 1} label`);
    const type = readColumnType(record.type, fieldIndex, index);
    return {
      key: readOptionalText(record.key, `Column key`, 80) ?? "",
      label,
      type,
      description: readOptionalText(record.description, `Column description`, 500),
    };
  });
}

function readFieldType(value: unknown, index: number): DocExtractFieldType {
  if (value === undefined || value === null || value === "") {
    return "text";
  }
  if (typeof value !== "string" || !FIELD_TYPES.has(value as DocExtractFieldType)) {
    throw new Error(`Field ${index + 1} has an unknown type.`);
  }
  return value as DocExtractFieldType;
}

function readColumnType(
  value: unknown,
  fieldIndex: number,
  columnIndex: number,
): DocExtractTableColumn["type"] {
  if (value === undefined || value === null || value === "") {
    return "text";
  }
  if (typeof value !== "string" || !COLUMN_TYPES.has(value as DocExtractTableColumn["type"])) {
    throw new Error(`Field ${fieldIndex + 1} column ${columnIndex + 1} has an unknown type.`);
  }
  return value as DocExtractTableColumn["type"];
}

function readStatus(value: unknown): DocExtractTemplateStatus {
  if (value === undefined || value === null || value === "") {
    return "draft";
  }
  if (value === "draft" || value === "published") {
    return value;
  }
  throw new Error("Template status must be draft or published.");
}

function readText(value: unknown, label: string): string {
  if (typeof value !== "string" || value.trim() === "") {
    throw new Error(`${label} is required.`);
  }
  return value.trim();
}

function readOptionalText(value: unknown, label: string, max: number): string | null {
  if (value === undefined || value === null || value === "") {
    return null;
  }
  if (typeof value !== "string") {
    throw new Error(`${label} must be text.`);
  }
  const trimmed = value.trim();
  if (trimmed.length > max) {
    throw new Error(`${label} is too long.`);
  }
  return trimmed === "" ? null : trimmed;
}

function slugifyFileName(name: string): string {
  const slug = name
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80);
  return slug || "doc-extract-template";
}
