"use client";

import { useEffect, useMemo, useState } from "react";

import { EApprovalComposeFormFields } from "@/components/e-approval/e-approval-compose-form-fields";
import { parseFormComposeConfig } from "@/modules/e-approval/form-compose-config";
import { applyComputedFieldValues } from "@/modules/e-approval/field-computed";
import { fieldDefaultValue } from "@/modules/e-approval/field-validation";
import { formUsesCashAdvanceParentPicker } from "@/modules/e-approval/parent-submission-link";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { E_APPROVAL_FORM_SHELL_CLASS } from "@/modules/e-approval/form-layout";

type Props = {
  formName: string;
  formDescription?: string | null;
  fields: EApprovalFormFieldInput[];
  approverOptions: { id: string; label: string }[];
  formMetadata?: Record<string, unknown> | null;
};

function initialPreviewValues(fields: EApprovalFormFieldInput[]): Record<string, string> {
  const values: Record<string, string> = {};
  for (const field of fields) {
    if (field.type === "grid") {
      values[field.name] = '{"rows":[{"0":""}]}';
    } else if (field.type === "tags") {
      values[field.name] = "";
    } else if (field.type === "location") {
      values[field.name] = "";
    } else if (field.type !== "section" && field.type !== "divider") {
      values[field.name] = fieldDefaultValue(field);
    }
  }
  return values;
}

export function EApprovalFormPreview({ formName, formDescription, fields, approverOptions, formMetadata = null }: Props) {
  const [values, setValues] = useState<Record<string, string>>(() => initialPreviewValues(fields));
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const composeConfig = useMemo(() => parseFormComposeConfig(formMetadata), [formMetadata]);

  const valuesKey = useMemo(() => fields.map((f) => `${f.name}:${fieldDefaultValue(f)}`).join("|"), [fields]);

  useEffect(() => {
    setValues(initialPreviewValues(fields));
    setFieldErrors({});
  }, [valuesKey, fields]);

  const hasFields = fields.some((f) => f.type !== "section" && f.type !== "divider");
  const computedValues = useMemo(
    () => (fields.length > 0 ? applyComputedFieldValues(fields, values) : values),
    [fields, values],
  );
  const usesCashAdvancePicker = formUsesCashAdvanceParentPicker(formMetadata);

  return (
    <div className={E_APPROVAL_FORM_SHELL_CLASS}>
      <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
        <h2 className="text-xl font-semibold text-foreground">{formName.trim() || "Untitled form"}</h2>
        {formDescription?.trim() ? (
          <p className="mt-1 text-sm text-muted-foreground">{formDescription.trim()}</p>
        ) : (
          <p className="mt-1 text-sm text-muted-foreground">Preview mode — this is how requestors will see the form.</p>
        )}
      </div>

      <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
        {!hasFields ? (
          <p className="text-sm text-muted-foreground">Add fields on the Design tab to preview the requestor experience.</p>
        ) : (
          <div className="space-y-4">
            {usesCashAdvancePicker ? (
              <div className="rounded-lg border border-border bg-muted/20 px-3 py-2.5">
                <p className="text-sm font-medium text-foreground">Cash advance to liquidate</p>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  When you submit a request, you pick an approved cash advance here. The document number fills in
                  automatically.
                </p>
              </div>
            ) : null}
            <EApprovalComposeFormFields
              fields={fields}
              values={computedValues}
              fieldErrors={fieldErrors}
              composeConfig={composeConfig}
              onStepValidationIssues={(issues) => {
                const map: Record<string, string> = {};
                for (const issue of issues) {
                  map[issue.fieldName] = issue.message;
                }
                setFieldErrors(map);
              }}
              onChange={(name, next) => {
                setValues((prev) => ({ ...prev, [name]: next }));
                setFieldErrors((prev) => {
                  if (!prev[name]) {
                    return prev;
                  }
                  const nextErrors = { ...prev };
                  delete nextErrors[name];
                  return nextErrors;
                });
              }}
              approverOptions={approverOptions}
              density="comfortable"
              formMetadata={formMetadata}
            />
          </div>
        )}
      </div>
    </div>
  );
}
