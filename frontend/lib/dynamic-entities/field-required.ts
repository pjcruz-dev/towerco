import type { DynField } from "@/lib/api/modules/dynamic-entities-api";

export type RequiredFieldRef = Pick<DynField, "name" | "label" | "type" | "is_required">;

/** Every required field that is still blank. File and automatic-id fields are filled after save. */
export function missingRequiredFields(
  fields: RequiredFieldRef[],
  values: Record<string, string | undefined>,
): RequiredFieldRef[] {
  return fields.filter((field) => {
    if (!field.is_required) return false;
    if (field.type === "automatic_id" || field.type === "file") return false;
    return (values[field.name]?.trim() ?? "") === "";
  });
}
