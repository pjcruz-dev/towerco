import type { ControlledDocumentSyncMeta } from "@/modules/e-approval/controlled-document-sync";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

/**
 * Controlled-document registry lookup was removed with the Documents module.
 * Kept as a no-op so compose flows still compile without registry API calls.
 */
export function applyControlledDocumentLookupPrefill(
  _sync: ControlledDocumentSyncMeta,
  _fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
  _lookup?: unknown,
  _options?: { overwrite?: boolean },
): Record<string, string> {
  return values;
}
