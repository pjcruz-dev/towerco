import { parseFieldValidation } from "@/modules/e-approval/field-validation";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export function attachmentCountsByField(
  attachments: Array<{ field_name: string }>,
): Record<string, number> {
  const counts: Record<string, number> = {};

  for (const attachment of attachments) {
    const fieldName = attachment.field_name.trim();
    if (fieldName === "") {
      continue;
    }

    counts[fieldName] = (counts[fieldName] ?? 0) + 1;
  }

  return counts;
}

export function missingRequiredAttachmentFieldLabels(
  fields: EApprovalFormFieldInput[],
  attachments: Array<{ field_name: string }>,
): string[] {
  const counts = attachmentCountsByField(attachments);
  const missing: string[] = [];

  for (const field of fields) {
    if (field.type !== "file") {
      continue;
    }

    const rules = parseFieldValidation(field);
    if (!rules.required || (counts[field.name] ?? 0) > 0) {
      continue;
    }

    missing.push(field.label?.trim() || field.name);
  }

  return missing;
}
