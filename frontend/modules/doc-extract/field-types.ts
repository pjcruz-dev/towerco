import type { DocExtractFieldType, DocExtractTableColumn } from "@/modules/doc-extract/types";

export const DOC_EXTRACT_FIELD_TYPES: Array<{
  value: DocExtractFieldType;
  label: string;
  short: string;
  description: string;
}> = [
  { value: "text", label: "Text", short: "T text", description: "Names, IDs, free text" },
  { value: "multiline", label: "Multiline", short: "Aa text", description: "Addresses, notes" },
  { value: "number", label: "Number", short: "# number", description: "Quantities, counts" },
  { value: "currency", label: "Currency", short: "$ amount", description: "Money amounts" },
  { value: "percentage", label: "Percentage", short: "% pct", description: "Rates, VAT %" },
  { value: "date", label: "Date", short: "Date", description: "Invoice / due dates" },
  { value: "email", label: "Email", short: "Email", description: "Email addresses" },
  { value: "phone", label: "Phone", short: "Phone", description: "Phone / mobile" },
  { value: "boolean", label: "Yes / No", short: "Y/N", description: "Flags" },
  { value: "table", label: "Table", short: "table", description: "Nested rows (e.g. payment history)" },
];

export const DOC_EXTRACT_TABLE_COLUMN_TYPES = DOC_EXTRACT_FIELD_TYPES.filter(
  (entry) => !["table", "multiline", "email", "phone"].includes(entry.value),
);

/** Stable export column key from a display label (snake_case). */
export function slugifyDocExtractKey(label: string): string {
  return label
    .trim()
    .toLowerCase()
    .replace(/['"]/g, "")
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "")
    .replace(/_+/g, "_")
    .slice(0, 80);
}

export function formatDocExtractFieldTypeLabel(type: DocExtractFieldType): string {
  return DOC_EXTRACT_FIELD_TYPES.find((entry) => entry.value === type)?.label ?? type;
}

export function formatDocExtractFieldTypeShort(type: DocExtractFieldType): string {
  return DOC_EXTRACT_FIELD_TYPES.find((entry) => entry.value === type)?.short ?? type;
}

export function emptyTableColumn(): DocExtractTableColumn {
  return { key: "", label: "", type: "text", description: "" };
}

export function docExtractInputMode(
  type: DocExtractFieldType,
): "decimal" | "email" | "tel" | undefined {
  switch (type) {
    case "number":
    case "currency":
    case "percentage":
      return "decimal";
    case "email":
      return "email";
    case "phone":
      return "tel";
    default:
      return undefined;
  }
}

export function docExtractHtmlInputType(type: DocExtractFieldType, value?: string): string {
  switch (type) {
    case "date":
      return value && /^\d{4}-\d{2}-\d{2}$/.test(value) ? "date" : "text";
    case "email":
      return "email";
    case "phone":
      return "tel";
    default:
      return "text";
  }
}
