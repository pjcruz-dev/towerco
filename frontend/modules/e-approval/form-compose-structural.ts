import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

/** Layout / compose fields that are not filled in by requestors. */
export const COMPOSE_STRUCTURAL_FIELD_TYPES = ["section", "divider", "page_break", "instruction"] as const;

export type ComposeStructuralFieldType = (typeof COMPOSE_STRUCTURAL_FIELD_TYPES)[number];

export type FormComposeStepSource = "sections" | "page_breaks" | "auto";

export function isComposeStructuralFieldType(type: string): boolean {
  return (COMPOSE_STRUCTURAL_FIELD_TYPES as readonly string[]).includes(type);
}

export function isComposeFillableFieldType(type: string): boolean {
  return !isComposeStructuralFieldType(type);
}

export function formHasPageBreakFields(fields: { type: string }[]): boolean {
  return fields.some((field) => field.type === "page_break");
}

export function resolveEffectiveStepSource(
  fields: EApprovalFormFieldInput[],
  stepSource: FormComposeStepSource,
): "sections" | "page_breaks" {
  if (stepSource === "page_breaks") {
    return "page_breaks";
  }

  if (stepSource === "auto" && formHasPageBreakFields(fields)) {
    return "page_breaks";
  }

  return "sections";
}
