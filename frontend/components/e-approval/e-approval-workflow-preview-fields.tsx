"use client";

import { useEApprovalFieldChoices } from "@/hooks/use-e-approval-field-choices";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  value: string;
  onChange: (value: string) => void;
};

function WorkflowPreviewFieldControl({ field, value, onChange }: Props) {
  const { choices, isLoading } = useEApprovalFieldChoices(field);

  if ((field.type === "select" || field.type === "radio") && choices.length > 0) {
    return (
      <Select value={value} onChange={(e) => onChange(e.target.value)} disabled={isLoading}>
        <option value="">{isLoading ? "Loading…" : "Select value"}</option>
        {choices.map((choice) => (
          <option key={choice.value} value={choice.value}>
            {choice.label}
          </option>
        ))}
      </Select>
    );
  }

  return (
    <Input
      value={value}
      placeholder={`Sample ${field.label}`}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}

type ListProps = {
  fields: EApprovalFormFieldInput[];
  fieldNames: string[];
  values: Record<string, string>;
  onChange: (values: Record<string, string>) => void;
};

export function EApprovalWorkflowPreviewFields({ fields, fieldNames, values, onChange }: ListProps) {
  if (fieldNames.length === 0) {
    return (
      <p className="text-xs text-muted-foreground">
        Add workflow steps or form fields to preview with sample values.
      </p>
    );
  }

  return (
    <div className="grid gap-2 md:grid-cols-2">
      {fieldNames.map((fieldName) => {
        const field = fields.find((item) => item.name === fieldName);
        if (!field) {
          return null;
        }

        return (
          <label key={fieldName} className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">{field.label || fieldName}</span>
            <WorkflowPreviewFieldControl
              field={field}
              value={values[fieldName] ?? ""}
              onChange={(nextValue) => onChange({ ...values, [fieldName]: nextValue })}
            />
          </label>
        );
      })}
    </div>
  );
}
