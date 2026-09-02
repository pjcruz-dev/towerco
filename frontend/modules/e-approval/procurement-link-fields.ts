import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

/** Procurement entity link fields were removed with Procurement One. */
export function isProcurementLinkField(_field: EApprovalFormFieldInput): boolean {
  return false;
}

export function procurementLinkCascadePatch(
  _fieldName: string,
  _value: string,
): Record<string, string> {
  return {};
}
