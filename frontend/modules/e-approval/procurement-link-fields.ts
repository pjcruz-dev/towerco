import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { getMasterDataLookupKey, parseSelectChoices } from "@/modules/e-approval/field-options";

export const PROCUREMENT_LINK_FIELD_NAMES = [
  "project_id",
  "rollout_id",
  "site_id",
  "boq_line_id",
] as const;

export type ProcurementLinkFieldName = (typeof PROCUREMENT_LINK_FIELD_NAMES)[number];

/**
 * Reserved API-key names for live procurement entity pickers.
 * Select/radio/checkbox fields with master-data or static choices keep the normal choice UI
 * (e.g. public Site ID master-data set) instead of authenticated `/sites` lookups.
 */
export function isProcurementLinkField(field: EApprovalFormFieldInput): field is EApprovalFormFieldInput & {
  name: ProcurementLinkFieldName;
} {
  if (!PROCUREMENT_LINK_FIELD_NAMES.includes(field.name as ProcurementLinkFieldName)) {
    return false;
  }

  if (getMasterDataLookupKey(field)) {
    return false;
  }

  if (parseSelectChoices(field).length > 0) {
    return false;
  }

  return true;
}

/** Fields cleared when a parent link field changes. */
export function procurementLinkCascadePatch(fieldName: string, value: string): Record<string, string> {
  const patch: Record<string, string> = { [fieldName]: value };

  if (fieldName === "project_id") {
    patch.rollout_id = "";
    patch.site_id = "";
    patch.boq_line_id = "";
  } else if (fieldName === "rollout_id") {
    patch.boq_line_id = "";
  } else if (fieldName === "site_id") {
    patch.project_id = "";
  }

  return patch;
}
