"use client";

import { useQuery } from "@tanstack/react-query";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { fetchEApprovalMasterData } from "@/lib/api/modules/e-approval-api";
import { parseSelectChoices } from "@/modules/e-approval/field-options";
import { trainingTypesList, type FormWorkflowOptionSettings } from "@/modules/e-approval/form-workflow-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  value: FormWorkflowOptionSettings;
  onChange: (next: FormWorkflowOptionSettings) => void;
  fields?: EApprovalFormFieldInput[];
  disabled?: boolean;
};

const SKIPPED_FIELD_TYPES = new Set(["section", "heading", "divider", "file", "camera", "signature"]);

export function EApprovalFormWorkflowOptionsCard({ value, onChange, fields = [], disabled }: Props) {
  return (
    <EApprovalSectionCard
      title="Workflow options"
      description="These stay off until you turn them on. Save the form. You do not need to publish."
    >
      <div className="flex items-start justify-between gap-3 rounded-lg border border-border bg-muted/20 px-3 py-3">
        <div className="space-y-1">
          <Label htmlFor="ea-optional-approver">Optional approver</Label>
          <p className="text-xs text-muted-foreground">
            Mark the step Optional on the path, then save. The approver before that step can finish the request or send it on. While this is off, that step still runs. Requests already in progress pick this up after you save.
          </p>
        </div>
        <Switch
          id="ea-optional-approver"
          checked={value.optionalApprover}
          disabled={disabled}
          onCheckedChange={(checked) => onChange({ ...value, optionalApprover: Boolean(checked) })}
        />
      </div>
      <div className="mt-3 flex items-start justify-between gap-3 rounded-lg border border-border bg-muted/20 px-3 py-3">
        <div className="min-w-0 space-y-1">
          <Label htmlFor="ea-request-comments">Comments on the request</Label>
          <p className="text-xs text-muted-foreground">
            Drag on a value in Request details, the same way you mark a file. The note can mention someone with @. The person deciding can also Approve, Reject, or request Revision from that mark.
          </p>
        </div>
        <Switch
          id="ea-request-comments"
          checked={value.requestComments}
          disabled={disabled}
          onCheckedChange={(checked) => onChange({ ...value, requestComments: Boolean(checked) })}
        />
      </div>
      <div className="mt-3 rounded-lg border border-border bg-muted/20 px-3 py-3">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 space-y-1">
            <Label htmlFor="ea-mandatory-training">Mandatory training</Label>
            <p className="text-xs text-muted-foreground">
              After the last approval, the requestor invites people on that request. This works on any form. Leave the field on Every request, or limit it to one field on this form.
            </p>
          </div>
          <Switch
            id="ea-mandatory-training"
            checked={value.mandatoryTraining}
            disabled={disabled}
            onCheckedChange={(checked) => onChange({ ...value, mandatoryTraining: Boolean(checked) })}
          />
        </div>
        {value.mandatoryTraining ? (
          <TrainingScope value={value} fields={fields} disabled={disabled} onChange={onChange} />
        ) : null}
      </div>
    </EApprovalSectionCard>
  );
}

function TrainingScope({
  value,
  fields,
  disabled,
  onChange,
}: {
  value: FormWorkflowOptionSettings;
  fields: EApprovalFormFieldInput[];
  disabled?: boolean;
  onChange: (next: FormWorkflowOptionSettings) => void;
}) {
  const choices = fields.filter((field) => field.name.trim() !== "" && !SKIPPED_FIELD_TYPES.has(field.type));
  const selected = choices.find((field) => field.name === value.trainingField) ?? null;
  const masterKey = selected ? masterDataKey(selected) : "";
  const masterQuery = useQuery({
    queryKey: ["e-approval", "master-data", masterKey, "training-scope"],
    queryFn: () => fetchEApprovalMasterData(masterKey),
    enabled: masterKey !== "",
    staleTime: 60_000,
  });
  const staticOptions = selected ? parseSelectChoices(selected) : [];
  const options =
    masterQuery.data?.options?.map((option) => ({
      value: option.value || option.code || option.label,
      label: option.label || option.value,
    })) ?? staticOptions;
  const selectedValues = new Set(trainingTypesList(value.trainingValues).map((item) => item.toLowerCase()));

  return (
    <div className="mt-3 space-y-3">
      <div className="space-y-1.5">
        <Label htmlFor="ea-training-field">Applies to</Label>
        <Select
          id="ea-training-field"
          value={value.trainingField}
          disabled={disabled}
          onChange={(event) => onChange({ ...value, trainingField: event.target.value, trainingValues: "" })}
        >
          <option value="">Every request</option>
          {choices.map((field) => (
            <option key={field.name} value={field.name}>
              {field.label?.trim() || field.name}
            </option>
          ))}
        </Select>
        <p className="text-xs text-muted-foreground">
          {value.trainingField === ""
            ? "Every request on this form waits for training. A cash advance form can use this with no document type."
            : "Only requests whose answer matches stay for training. Other answers publish when the last approval finishes."}
        </p>
      </div>
      {selected && masterQuery.isError ? (
        <p className="text-sm text-destructive">The answers for {selected.label || selected.name} did not load. Refresh and choose the field again.</p>
      ) : null}
      {selected && masterQuery.isLoading ? (
        <p className="text-sm text-muted-foreground">Loading {selected.label || selected.name}…</p>
      ) : null}
      {selected && !masterQuery.isLoading && options.length > 0 ? (
        <fieldset className="space-y-2" disabled={disabled}>
          <legend className="text-sm font-medium text-foreground">Matching answers</legend>
          {options.map((option) => {
            const token = option.label.trim() || option.value;
            const checked = selectedValues.has(token.toLowerCase()) || selectedValues.has(option.value.toLowerCase());
            return (
              <label key={option.value} className="flex items-center gap-2 text-sm text-foreground">
                <input
                  type="checkbox"
                  className="size-4 rounded border-border"
                  checked={checked}
                  onChange={(event) => {
                    const next = new Set(trainingTypesList(value.trainingValues));
                    if (event.target.checked) {
                      next.add(token);
                      if (option.value && option.value !== token) {
                        next.add(option.value);
                      }
                    } else {
                      next.delete(token);
                      next.delete(option.value);
                    }
                    onChange({ ...value, trainingValues: [...next].join("\n") });
                  }}
                />
                {option.label}
              </label>
            );
          })}
          <p className="text-xs text-muted-foreground">Leave every box clear and every answer on this field waits for training.</p>
        </fieldset>
      ) : null}
      {selected && !masterQuery.isLoading && !masterQuery.isError && options.length === 0 ? (
        <div className="space-y-1.5">
          <Label htmlFor="ea-training-values">Matching answers</Label>
          <Textarea
            id="ea-training-values"
            value={value.trainingValues}
            disabled={disabled}
            rows={3}
            placeholder="One answer per line"
            onChange={(event) => onChange({ ...value, trainingValues: event.target.value })}
          />
          <p className="text-xs text-muted-foreground">Leave this empty and every answer on that field waits for training.</p>
        </div>
      ) : null}
    </div>
  );
}

function masterDataKey(field: EApprovalFormFieldInput): string {
  const options = field.options;
  if (!options || Array.isArray(options)) {
    return "";
  }
  const key = options.master_data_key;
  return typeof key === "string" ? key.trim() : "";
}
