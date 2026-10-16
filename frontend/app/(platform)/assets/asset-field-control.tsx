"use client";

import { DynRelationshipPicker } from "@/components/dynamic-entities/dyn-relationship-field";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { SelectField } from "@/components/ui/select-field";
import { Textarea } from "@/components/ui/textarea";
import type { DynField } from "@/lib/api/modules/dynamic-entities-api";
import { parseDynSelectOptions } from "@/lib/dynamic-entities/select-choices";

export const WRITABLE_SYSTEM = new Set([
  "serial_number",
  "asset_type",
  "status",
  "manufacturer",
  "product_name",
  "product_version",
  "hostname",
  "processor",
  "installed_ram",
  "operating_system",
  "system_type",
  "bios_version",
  "purchase_date",
  "warranty_end",
  "commission_date",
  "notes",
]);

const ASSET_TYPE_OPTIONS = [
  { value: "laptop", label: "Laptop" },
  { value: "desktop", label: "Desktop" },
  { value: "monitor", label: "Monitor" },
  { value: "dock", label: "Dock" },
  { value: "phone", label: "Phone" },
  { value: "peripheral", label: "Peripheral" },
];

const ASSET_STATUS_OPTIONS = [
  "NEW",
  "AVAILABLE",
  "ASSIGNED",
  "UNDER_REPAIR",
  "REPLACEMENT_PENDING",
  "LOST",
  "STOLEN",
  "RETIRED",
  "E_WASTE",
  "DISPOSED",
].map((value) => ({ value, label: value.replaceAll("_", " ") }));

const CREATE_STATUS_OPTIONS = [
  { value: "AVAILABLE", label: "Available" },
  { value: "NEW", label: "New" },
];

export function coerceAssetField(field: DynField, raw: string): unknown {
  if (raw === "") return null;
  if (field.type === "number" || field.type === "decimal") {
    const number = Number(raw);
    return Number.isFinite(number) ? number : null;
  }
  if (field.type === "boolean") return raw === "true" || raw === "1";
  return raw;
}

export function buildAssetWriteBody(
  fields: DynField[],
  draft: Record<string, string>,
  existingCustom: Record<string, unknown> = {},
): Record<string, unknown> {
  const body: Record<string, unknown> = {};
  const custom: Record<string, unknown> = { ...existingCustom };
  for (const field of fields) {
    const raw = draft[field.name];
    if (raw === undefined) continue;
    const value = coerceAssetField(field, raw);
    if (field.is_system_field) {
      if (!WRITABLE_SYSTEM.has(field.name)) continue;
      body[field.name] = value;
    } else {
      custom[field.name] = value;
    }
  }
  body.custom_values = custom;
  return body;
}

function selectOptionsFor(field: DynField, mode: "create" | "edit"): Array<{ value: string; label: string }> {
  if (mode === "create" && field.name === "status") return CREATE_STATUS_OPTIONS;
  const choices = parseDynSelectOptions(field.options).choices;
  if (choices.length > 0) {
    return choices.map((choice) => ({ value: choice.value, label: choice.label }));
  }
  if (field.name === "asset_type") return ASSET_TYPE_OPTIONS;
  if (field.name === "status") return ASSET_STATUS_OPTIONS;
  return [];
}

export function AssetFieldInput({
  field,
  value,
  onChange,
  mode,
}: {
  field: DynField;
  value: string;
  onChange: (next: string) => void;
  mode: "create" | "edit";
}) {
  if (field.name === "asset_tag") {
    return (
      <Input
        value={mode === "create" ? "" : value}
        readOnly
        disabled
        placeholder="Issued on save"
        className="bg-muted/40 font-mono"
      />
    );
  }
  if (field.is_system_field && !WRITABLE_SYSTEM.has(field.name)) {
    return <Input value={value} readOnly disabled className="bg-muted/40" />;
  }
  if (field.type === "select" || field.type === "multiselect") {
    const options = selectOptionsFor(field, mode);
    if (options.length > 0) {
      return (
        <SelectField
          value={value}
          onChange={onChange}
          options={options}
          allowEmpty={!field.is_required}
          placeholder="Select…"
        />
      );
    }
  }
  if (field.type === "textarea") {
    return (
      <Textarea className="min-h-20" value={value} onChange={(event) => onChange(event.target.value)} required={field.is_required} />
    );
  }
  if (field.type === "boolean") {
    const checked = value === "true" || value === "1";
    return (
      <label className="inline-flex h-9 items-center gap-2 text-sm">
        <Checkbox checked={checked} onCheckedChange={(next) => onChange(next === true ? "true" : "false")} />
        <span className="text-muted-foreground">{checked ? "Yes" : "No"}</span>
      </label>
    );
  }
  if (field.type === "date") {
    return <DatePicker value={value} onChange={onChange} />;
  }
  if (field.type === "datetime") {
    return (
      <Input
        type="datetime-local"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        required={field.is_required}
      />
    );
  }
  if (field.type === "email" || field.type === "phone") {
    return (
      <Input
        type={field.type === "email" ? "email" : "tel"}
        inputMode={field.type === "phone" ? "tel" : "email"}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        required={field.is_required}
      />
    );
  }
  if (field.type === "relationship") {
    return <DynRelationshipPicker field={field} value={value} onChange={onChange} required={field.is_required} />;
  }
  if (field.type === "file") {
    return <p className="text-sm text-muted-foreground">Attach the file from the asset record after it is saved.</p>;
  }
  return (
    <Input
      type={field.type === "number" || field.type === "decimal" ? "number" : "text"}
      value={value}
      onChange={(event) => onChange(event.target.value)}
      required={field.is_required}
      className={field.name === "serial_number" ? "font-mono" : undefined}
    />
  );
}
