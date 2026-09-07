"use client";

import { DatePicker } from "@/components/ui/date-picker";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { SelectField } from "@/components/ui/select-field";
import { Textarea } from "@/components/ui/textarea";
import { useEApprovalFieldChoices } from "@/hooks/use-e-approval-field-choices";
import {
  gridColumnAsSelectField,
  type GridColumnDef,
} from "@/modules/e-approval/field-options";

type Props = {
  column: GridColumnDef;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  comfortable?: boolean;
  allowRemoteLookups?: boolean;
};

export function EApprovalGridCell({
  column,
  value,
  onChange,
  disabled,
  comfortable,
  allowRemoteLookups = true,
}: Props) {
  const inputClass = comfortable
    ? "h-10 w-full min-w-0 text-sm"
    : "h-7 w-full min-w-0 px-1.5 text-[11px]";

  if (column.type === "select") {
    return (
      <GridSelectCell
        column={column}
        value={value}
        onChange={onChange}
        disabled={disabled}
        inputClass={inputClass}
        allowRemoteLookups={allowRemoteLookups}
      />
    );
  }

  if (column.type === "boolean") {
    const checked = value === "true" || value === "1" || value.toLowerCase() === "yes";
    return (
      <label className="inline-flex h-7 items-center gap-1.5 text-[11px]">
        <Checkbox
          disabled={disabled}
          checked={checked}
          onCheckedChange={(next) => onChange(next === true ? "true" : "false")}
        />
        <span className="text-muted-foreground">{checked ? "Yes" : "No"}</span>
      </label>
    );
  }

  if (column.type === "number" || column.type === "currency") {
    return (
      <Input
        disabled={disabled}
        type="number"
        className={inputClass}
        value={value}
        step={column.type === "currency" ? "0.01" : undefined}
        placeholder={column.type === "currency" ? "0.00" : undefined}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  if (column.type === "date") {
    return (
      <DatePicker
        disabled={disabled}
        value={value}
        onChange={onChange}
        className={inputClass}
        placeholder="Select date"
      />
    );
  }

  if (column.type === "textarea") {
    return (
      <Textarea
        disabled={disabled}
        className={
          comfortable
            ? "min-h-16 w-full min-w-[10rem] text-sm"
            : "min-h-9 w-full min-w-[7rem] px-1.5 py-1 text-[11px]"
        }
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  if (column.type === "email") {
    return (
      <Input
        disabled={disabled}
        type="email"
        className={inputClass}
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  if (column.type === "phone") {
    return (
      <Input
        disabled={disabled}
        type="tel"
        className={inputClass}
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  return (
    <Input
      disabled={disabled}
      className={inputClass}
      value={value}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}

function GridSelectCell({
  column,
  value,
  onChange,
  disabled,
  inputClass,
  allowRemoteLookups = true,
}: Props & { inputClass: string }) {
  const syntheticField = gridColumnAsSelectField(column);
  const { choices, isLoading, isError } = useEApprovalFieldChoices(
    syntheticField,
    !disabled,
    allowRemoteLookups,
  );
  const emptyLabel = isLoading ? "Loading…" : "Select…";

  return (
    <>
      <SelectField
        disabled={disabled || isLoading}
        className={inputClass}
        value={value}
        onChange={onChange}
        placeholder={emptyLabel}
        emptyLabel={emptyLabel}
        options={choices.map((c) => ({
          value: c.value,
          label: c.label,
        }))}
      />
      {isError ? <span className="sr-only">Options failed to load</span> : null}
    </>
  );
}
