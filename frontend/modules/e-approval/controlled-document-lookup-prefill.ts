import type { ControlledDocumentSyncMeta } from "@/modules/e-approval/controlled-document-sync";
import type { ControlledDocumentLookupResult } from "@/lib/api/modules/controlled-documents-api";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { applyComputedFieldValues } from "@/modules/e-approval/field-computed";

export function applyControlledDocumentLookupPrefill(
  sync: ControlledDocumentSyncMeta,
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
  lookup: ControlledDocumentLookupResult,
  options: { overwrite?: boolean } = {},
): Record<string, string> {
  if (!lookup.exists) {
    return values;
  }

  const overwrite = options.overwrite ?? true;
  const patch: Record<string, string> = {
    [sync.documentCodeField]: lookup.document_code ?? values[sync.documentCodeField] ?? "",
    [sync.revisionFieldName]: String(lookup.next_revision),
  };

  const assign = (fieldKey: keyof ControlledDocumentSyncMeta["fieldMap"], raw: string | null | undefined) => {
    const fieldName = sync.fieldMap[fieldKey];
    if (!fieldName || raw == null || raw === "") {
      return;
    }

    if (!overwrite && (values[fieldName] ?? "").trim() !== "") {
      return;
    }

    patch[fieldName] = raw;
  };

  assign("title", lookup.title);
  assign("document_type", lookup.document_type);
  assign("department", lookup.department);
  assign("effective_date", lookup.effective_date ?? undefined);
  assign("next_review_date", lookup.next_review_date ?? undefined);

  const previousRevisionField = fields.find((field) => field.name === "previous_revision");
  if (
    previousRevisionField &&
    lookup.current_revision !== undefined &&
    (overwrite || (values.previous_revision ?? "").trim() === "")
  ) {
    patch.previous_revision = String(lookup.current_revision);
  }

  return applyComputedFieldValues(fields, { ...values, ...patch });
}
