"use client";

import { useMemo } from "react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import {
  CONTROLLED_DOCUMENT_FIELD_DEFINITIONS,
  controlledDocumentSyncReadiness,
  fieldsForControlledDocumentSlot,
  suggestControlledDocumentSyncSettings,
  type ControlledDocumentSyncEditorSettings,
  type ControlledDocumentSyncFieldKey,
} from "@/modules/e-approval/form-controlled-document-sync";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  value: ControlledDocumentSyncEditorSettings;
  onChange: (next: ControlledDocumentSyncEditorSettings) => void;
  fields: EApprovalFormFieldInput[];
  disabled?: boolean;
};

function FieldMapSelect({
  id,
  label,
  value,
  options,
  disabled,
  onChange,
}: {
  id: string;
  label: string;
  value: string;
  options: EApprovalFormFieldInput[];
  disabled?: boolean;
  onChange: (fieldName: string) => void;
}) {
  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <Select
        id={id}
        value={value}
        disabled={disabled}
        onChange={(event) => onChange(event.target.value)}
      >
        {options.length === 0 ? <option value={value}>{value || "—"}</option> : null}
        {options.map((field) => (
          <option key={field.name} value={field.name}>
            {field.label?.trim() || field.name} ({field.name})
          </option>
        ))}
      </Select>
    </div>
  );
}

export function EApprovalFormControlledDocumentSyncCard({ value, onChange, fields, disabled }: Props) {
  const patch = (partial: Partial<ControlledDocumentSyncEditorSettings>) => {
    onChange({ ...value, ...partial });
  };

  const patchFieldMap = (key: ControlledDocumentSyncFieldKey, fieldName: string) => {
    onChange({
      ...value,
      fieldMap: {
        ...value.fieldMap,
        [key]: fieldName,
      },
    });
  };

  const readiness = useMemo(() => controlledDocumentSyncReadiness(value, fields), [value, fields]);

  const documentCodeOptions = fieldsForControlledDocumentSlot(fields, "document_code");
  const attachmentOptions = fieldsForControlledDocumentSlot(fields, "attachments");

  const handleAutoDetect = () => {
    onChange(suggestControlledDocumentSyncSettings(fields, value));
  };

  return (
    <EApprovalSectionCard
      title="Controlled document register"
      description="Publish approved submissions to Documents → Document register. New requests and revisions start from the register, not inside the form."
    >
      <label className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-3">
        <span>
          <span className="block text-sm font-medium text-foreground">Sync to document registry</span>
          <span className="mt-0.5 block text-xs text-muted-foreground">
            When enabled, final approval creates or updates a controlled document record.
          </span>
        </span>
        <Switch
          checked={value.enabled}
          disabled={disabled}
          onCheckedChange={(checked) => {
            if (checked) {
              onChange(suggestControlledDocumentSyncSettings(fields, { ...value, enabled: true }));
              return;
            }
            patch({ enabled: false });
          }}
        />
      </label>

      {value.enabled ? (
        <div className="mt-4 space-y-4">
          <div className="rounded-lg border border-border/80 bg-muted/20 px-3 py-3 text-xs text-muted-foreground">
            <p className="font-medium text-foreground">Workflow</p>
            <ul className="mt-2 list-disc space-y-1 pl-4">
              <li>
                <strong className="font-medium text-foreground">New document</strong> — requestors use{" "}
                <strong className="font-medium text-foreground">Document register → New controlled document</strong>.
                Document code is generated on submit.
              </li>
              <li>
                <strong className="font-medium text-foreground">Revision</strong> — start from the register row{" "}
                <strong className="font-medium text-foreground">Submit revision</strong>. The form opens with the
                document pre-selected; revision increments automatically.
              </li>
            </ul>
          </div>

          <label className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-3">
            <span>
              <span className="block text-sm font-medium text-foreground">Auto-assign revision numbers</span>
              <span className="mt-0.5 block text-xs text-muted-foreground">
                Leave the revision field blank on submit — the system sets 0 for new docs or current + 1 for revisions.
              </span>
            </span>
            <Switch
              checked={value.autoRevision}
              disabled={disabled}
              onCheckedChange={(checked) => patch({ autoRevision: checked })}
            />
          </label>

          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-xs font-medium text-foreground">Map form fields to registry columns</p>
            <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={handleAutoDetect}>
              Auto-detect from form
            </Button>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            {CONTROLLED_DOCUMENT_FIELD_DEFINITIONS.map((definition) => {
              const options = fieldsForControlledDocumentSlot(fields, definition.key);
              return (
                <FieldMapSelect
                  key={definition.key}
                  id={`ea-cd-map-${definition.key}`}
                  label={definition.label}
                  value={value.fieldMap[definition.key]}
                  options={options}
                  disabled={disabled}
                  onChange={(fieldName) => patchFieldMap(definition.key, fieldName)}
                />
              );
            })}

          <div className="space-y-2 md:col-span-2">
            <p className="text-xs font-medium text-foreground">Document code field</p>
            <p className="text-xs text-muted-foreground">
              Hidden on the request form when launched from the register. Used internally for registry sync and revision
              deep links.
            </p>
            <FieldMapSelect
              id="ea-cd-map-document-code"
              label="Map to form field"
              value={value.documentCodeField}
              options={documentCodeOptions}
              disabled={disabled}
              onChange={(fieldName) => patch({ documentCodeField: fieldName })}
            />
          </div>

            <FieldMapSelect
              id="ea-cd-map-attachments"
              label="Attachments"
              value={value.attachmentField}
              options={attachmentOptions}
              disabled={disabled}
              onChange={(fieldName) => patch({ attachmentField: fieldName })}
            />
          </div>

          <p className="text-xs text-muted-foreground">
            Configure who can see which register rows under{" "}
            <strong className="font-medium text-foreground">Documents → Controlled Document Register → Register access</strong>.
          </p>

          {readiness.warnings.length > 0 ? (
            <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-3 text-xs text-amber-800 dark:text-amber-200">
              <p className="font-medium">Setup checklist</p>
              <ul className="mt-1 list-disc space-y-1 pl-4">
                {readiness.warnings.map((warning) => (
                  <li key={warning}>{warning}</li>
                ))}
              </ul>
            </div>
          ) : (
            <p
              className={cn(
                "rounded-lg border px-3 py-2 text-xs",
                "border-emerald-500/30 bg-emerald-500/5 text-emerald-800 dark:text-emerald-200",
              )}
            >
              Field mapping looks complete. Save and publish after any Design changes.
            </p>
          )}
        </div>
      ) : null}
    </EApprovalSectionCard>
  );
}
