"use client";

import { useEffect, useMemo, useState } from "react";

import { EApprovalComposeFormFields } from "@/components/e-approval/e-approval-compose-form-fields";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  mergeFormComposeIntoMetadata,
  parseFormComposeConfig,
  type FormComposeEditorSettings,
} from "@/modules/e-approval/form-compose-config";
import { fieldDefaultValue } from "@/modules/e-approval/field-validation";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  formName: string;
  formDescription?: string | null;
  fields: EApprovalFormFieldInput[];
  approverOptions: { id: string; label: string }[];
  composeSettings: FormComposeEditorSettings;
  metadata: Record<string, unknown>;
};

function initialPreviewValues(fields: EApprovalFormFieldInput[]): Record<string, string> {
  const values: Record<string, string> = {};
  for (const field of fields) {
    if (field.type === "grid") {
      values[field.name] = '{"rows":[{"0":""}]}';
    } else if (field.type === "tags" || field.type === "location") {
      values[field.name] = "";
    } else if (field.type !== "section" && field.type !== "divider") {
      values[field.name] = fieldDefaultValue(field);
    }
  }
  return values;
}

export function EApprovalFormComposePreviewDialog({
  open,
  onOpenChange,
  formName,
  formDescription,
  fields,
  approverOptions,
  composeSettings,
  metadata,
}: Props) {
  const previewMetadata = useMemo(
    () => mergeFormComposeIntoMetadata(metadata, composeSettings),
    [composeSettings, metadata],
  );
  const composeConfig = useMemo(() => parseFormComposeConfig(previewMetadata), [previewMetadata]);
  const [values, setValues] = useState<Record<string, string>>(() => initialPreviewValues(fields));
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (!open) {
      return;
    }
    setValues(initialPreviewValues(fields));
    setFieldErrors({});
  }, [fields, open]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex h-[min(96vh,calc(100dvh-1rem))] w-[min(calc(100vw-1rem),1600px)] max-h-[min(96vh,calc(100dvh-1rem))] max-w-none flex-col overflow-hidden p-0">
        <DialogHeader className="shrink-0 border-b border-border px-6 py-4">
          <DialogTitle>{formName.trim() || "Requestor preview"}</DialogTitle>
          <p className="text-sm text-muted-foreground">
            {formDescription?.trim() || "Interact with the form as requestors will."}
            {composeSettings.mode === "stepped"
              ? composeSettings.validateOnNext
                ? " Stepped flow — use Next/Back; steps turn green only after validation."
                : " Stepped flow — click any step, or use Next and Back."
              : " Single-page scroll."}
          </p>
        </DialogHeader>
        <DialogBody className="min-h-0 flex-1 overflow-y-auto px-6 py-4">
          <EApprovalComposeFormFields
            fields={fields}
            values={values}
            fieldErrors={fieldErrors}
            composeConfig={composeConfig}
            formMetadata={previewMetadata}
            approverOptions={approverOptions}
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
          />
        </DialogBody>
      </DialogContent>
    </Dialog>
  );
}
