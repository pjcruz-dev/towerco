"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import { fetchTicketingSchema } from "@/lib/api/modules/ticketing-api";
import { parseDynSelectOptions } from "@/lib/dynamic-entities/select-choices";
import type { TicketingSchemaField } from "@/modules/ticketing/types";

export function useTicketingSchema() {
  return useQuery({
    queryKey: ["ticketing", "schema"],
    queryFn: fetchTicketingSchema,
    staleTime: 60_000,
  });
}

export function ticketingCustomFields(fields: TicketingSchemaField[] | undefined): TicketingSchemaField[] {
  return (fields ?? [])
    .filter((field) => !field.is_system_field)
    .sort((a, b) => a.field_order - b.field_order || a.label.localeCompare(b.label));
}

export function formatTicketingCustomValue(value: unknown): string {
  if (value == null || value === "") return "—";
  if (typeof value === "boolean") return value ? "Yes" : "No";
  if (Array.isArray(value)) return value.map((item) => String(item)).join(", ") || "—";
  return String(value);
}

export function ticketingCustomPayload(
  fields: TicketingSchemaField[],
  draft: Record<string, string>,
): Record<string, unknown> {
  const payload: Record<string, unknown> = {};
  for (const field of fields) {
    const raw = draft[field.name] ?? "";
    if (raw === "") {
      payload[field.name] = null;
      continue;
    }
    if (field.type === "number" || field.type === "decimal") {
      const number = Number(raw);
      payload[field.name] = Number.isFinite(number) ? number : raw;
      continue;
    }
    if (field.type === "boolean") {
      payload[field.name] = raw === "true";
      continue;
    }
    if (field.type === "multiselect") {
      payload[field.name] = raw.split(",").map((item) => item.trim()).filter(Boolean);
      continue;
    }
    payload[field.name] = raw;
  }
  return payload;
}

function draftValue(field: TicketingSchemaField, values: Record<string, unknown> | undefined): string {
  const raw = values?.[field.name];
  if (raw == null) return "";
  if (typeof raw === "boolean") return raw ? "true" : "false";
  if (Array.isArray(raw)) return raw.map((item) => String(item)).join(", ");
  return String(raw);
}

export function TicketingCustomFieldsInputs({
  draft,
  onChange,
  disabled,
}: {
  draft: Record<string, string>;
  onChange: (name: string, value: string) => void;
  disabled?: boolean;
}) {
  const { data, isLoading, isError } = useTicketingSchema();
  const fields = ticketingCustomFields(data?.fields);
  if (isLoading || isError || fields.length === 0) return null;

  return (
    <section className="space-y-4 border-t border-border pt-5">
      <h2 className="text-sm font-medium text-foreground">Additional details</h2>
      <div className="grid gap-4 sm:grid-cols-2">
        {fields.map((field) => (
          <div key={field.id} className={field.type === "textarea" ? "space-y-2 sm:col-span-2" : "space-y-2"}>
            <Label htmlFor={`ticket-field-${field.name}`}>
              {field.label}
              {field.is_required ? <span className="text-destructive"> *</span> : null}
            </Label>
            <TicketingCustomFieldControl
              field={field}
              value={draft[field.name] ?? ""}
              disabled={disabled}
              onChange={(next) => onChange(field.name, next)}
            />
          </div>
        ))}
      </div>
    </section>
  );
}

export function TicketingCustomFieldsEditor({
  stored,
  disabled,
  onSave,
}: {
  stored: Record<string, unknown> | undefined;
  disabled?: boolean;
  onSave: (payload: Record<string, unknown>) => Promise<void>;
}) {
  const { data, isLoading, isError } = useTicketingSchema();
  const fields = ticketingCustomFields(data?.fields);
  const [draft, setDraft] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const next: Record<string, string> = {};
    for (const field of fields) {
      next[field.name] = draftValue(field, stored);
    }
    setDraft(next);
  }, [stored, data?.fields]);

  if (isLoading || isError || fields.length === 0) return null;

  return (
    <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
      <h2 className="text-sm font-medium text-foreground">Additional details</h2>
      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        {fields.map((field) => (
          <div key={field.id} className={field.type === "textarea" ? "space-y-2 sm:col-span-2" : "space-y-2"}>
            <Label htmlFor={`ticket-detail-field-${field.name}`}>
              {field.label}
              {field.is_required ? <span className="text-destructive"> *</span> : null}
            </Label>
            <TicketingCustomFieldControl
              field={field}
              idPrefix="ticket-detail-field"
              value={draft[field.name] ?? ""}
              disabled={disabled || saving}
              onChange={(next) => setDraft((current) => ({ ...current, [field.name]: next }))}
            />
          </div>
        ))}
      </div>
      {error ? <p className="mt-3 text-sm text-destructive">{error}</p> : null}
      <div className="mt-4">
        <Button
          type="button"
          size="sm"
          disabled={disabled || saving}
          onClick={() => {
            setError(null);
            setSaving(true);
            void onSave(ticketingCustomPayload(fields, draft))
              .catch((saveError: unknown) => {
                setError(getErrorMessage(saveError) || "Could not save these fields.");
              })
              .finally(() => setSaving(false));
          }}
        >
          {saving ? "Saving…" : "Save details"}
        </Button>
      </div>
    </section>
  );
}

function TicketingCustomFieldControl({
  field,
  value,
  onChange,
  disabled,
  idPrefix = "ticket-field",
}: {
  field: TicketingSchemaField;
  value: string;
  onChange: (next: string) => void;
  disabled?: boolean;
  idPrefix?: string;
}) {
  const id = `${idPrefix}-${field.name}`;
  if (field.type === "file" || field.type === "automatic_id" || field.type === "relationship") {
    return (
      <p id={id} className="text-sm text-muted-foreground">
        {field.type === "file" ? "Add files with attachments." : "This field type is not filled in on the ticket form."}
      </p>
    );
  }
  if (field.type === "textarea") {
    return (
      <Textarea
        id={id}
        value={value}
        disabled={disabled}
        required={field.is_required}
        onChange={(event) => onChange(event.target.value)}
      />
    );
  }
  if (field.type === "boolean") {
    const checked = value === "true";
    return (
      <label htmlFor={id} className="inline-flex h-9 items-center gap-2 text-sm">
        <Checkbox
          id={id}
          checked={checked}
          disabled={disabled}
          onCheckedChange={(next) => onChange(next === true ? "true" : "false")}
        />
        <span className="text-muted-foreground">{checked ? "Yes" : "No"}</span>
      </label>
    );
  }
  if (field.type === "date") {
    return <DatePicker id={id} value={value} onChange={onChange} disabled={disabled} />;
  }
  if (field.type === "select" || field.type === "multiselect") {
    const choices = parseDynSelectOptions(field.options).choices;
    if (choices.length > 0 && field.type === "select") {
      return (
        <Select
          id={id}
          className="h-10"
          value={value}
          disabled={disabled}
          required={field.is_required}
          onChange={(event) => onChange(event.target.value)}
        >
          {!field.is_required ? <option value="">Select…</option> : null}
          {choices.map((choice) => (
            <option key={choice.value} value={choice.value}>
              {choice.label}
            </option>
          ))}
        </Select>
      );
    }
  }
  return (
    <Input
      id={id}
      type={field.type === "number" || field.type === "decimal" ? "number" : field.type === "email" ? "email" : field.type === "phone" ? "tel" : field.type === "datetime" ? "datetime-local" : "text"}
      inputMode={field.type === "phone" ? "tel" : field.type === "number" || field.type === "decimal" ? "decimal" : undefined}
      value={value}
      disabled={disabled}
      required={field.is_required}
      onChange={(event) => onChange(event.target.value)}
    />
  );
}
