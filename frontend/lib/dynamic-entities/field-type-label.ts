export const DYN_FIELD_TYPE_OPTIONS = [
  { value: "text", label: "Text" },
  { value: "textarea", label: "Long text" },
  { value: "number", label: "Number" },
  { value: "decimal", label: "Decimal" },
  { value: "date", label: "Date" },
  { value: "datetime", label: "Date & time" },
  { value: "select", label: "Dropdown" },
  { value: "multiselect", label: "Multi-select" },
  { value: "boolean", label: "Yes / No" },
  { value: "email", label: "Email" },
  { value: "phone", label: "Phone" },
  { value: "relationship", label: "Link to another Record" },
  { value: "child_list", label: "List of Child Records" },
  { value: "automatic_id", label: "Automatic ID" },
  { value: "file", label: "File" },
] as const;

export function dynFieldTypeLabel(type: string): string {
  return DYN_FIELD_TYPE_OPTIONS.find((item) => item.value === type)?.label ?? type.replaceAll("_", " ");
}
