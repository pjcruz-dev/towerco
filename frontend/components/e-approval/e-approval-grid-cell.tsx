"use client";

import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { SelectField } from "@/components/ui/select-field";
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
  const inputClass = comfortable ? "h-10 w-full min-w-0 text-sm" : "h-8 w-full min-w-0 text-xs";

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

  if (column.type === "number" || column.type === "currency") {
    return (
      <Input
        disabled={disabled}
        type="number"
        className={inputClass}
        value={value}
        step={column.type === "currency" ? "0.01" : undefined}
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
