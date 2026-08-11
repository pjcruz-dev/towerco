"use client";

import { Select as SelectPrimitive } from "@base-ui/react/select";
import { Check, ChevronDown } from "lucide-react";
import { useMemo } from "react";

import { fieldControlClassName } from "@/lib/ui/field-control";
import { cn } from "@/lib/utils";

export type SelectFieldOption = {
  value: string;
  label: string;
  disabled?: boolean;
};

type Props = {
  value: string;
  onChange: (value: string) => void;
  options: SelectFieldOption[];
  placeholder?: string;
  disabled?: boolean;
  className?: string;
  /** When true, include a clearable empty option. */
  allowEmpty?: boolean;
  emptyLabel?: string;
  id?: string;
  "aria-invalid"?: boolean;
};

export function SelectField({
  value,
  onChange,
  options,
  placeholder = "Select…",
  disabled,
  className,
  allowEmpty = true,
  emptyLabel,
  id,
  "aria-invalid": ariaInvalid,
}: Props) {
  const selected = value.trim() === "" ? null : value;
  const clearLabel = emptyLabel ?? placeholder;

  // Base UI <Select.Value> only shows labels when Root has `items`; otherwise the raw value (e.g. UUID) is shown.
  const items = useMemo(() => {
    const mapped = options.map((option) => ({
      value: option.value,
      label: option.label,
    }));

    if (!allowEmpty) {
      return mapped;
    }

    return [{ value: null as string | null, label: clearLabel }, ...mapped];
  }, [allowEmpty, clearLabel, options]);

  return (
    <SelectPrimitive.Root
      value={selected}
      onValueChange={(next) => onChange(next == null ? "" : String(next))}
      disabled={disabled}
      items={items}
    >
      <SelectPrimitive.Trigger
        id={id}
        aria-invalid={ariaInvalid}
        className={cn(
          fieldControlClassName,
          "flex h-9 items-center justify-between gap-2 text-left data-placeholder:text-muted-foreground",
          className,
        )}
      >
        <SelectPrimitive.Value placeholder={placeholder} className="min-w-0 flex-1 truncate" />
        <SelectPrimitive.Icon className="shrink-0 text-muted-foreground">
          <ChevronDown className="size-4 opacity-70" />
        </SelectPrimitive.Icon>
      </SelectPrimitive.Trigger>

      <SelectPrimitive.Portal>
        <SelectPrimitive.Positioner className="z-50 outline-none" sideOffset={6} align="start">
          <SelectPrimitive.Popup
            className={cn(
              "z-50 max-h-[min(20rem,var(--available-height))] w-[var(--anchor-width)] min-w-[12rem] origin-(--transform-origin)",
              "overflow-y-auto overscroll-contain rounded-xl border border-border bg-popover p-1 text-popover-foreground shadow-md outline-none",
              "transition duration-150 ease-out data-ending-style:scale-95 data-ending-style:opacity-0 data-starting-style:scale-95 data-starting-style:opacity-0",
            )}
          >
            <SelectPrimitive.List>
              {allowEmpty ? (
                <SelectPrimitive.Item
                  value={null}
                  label={emptyLabel ?? placeholder}
                  className={cn(
                    "relative flex cursor-pointer items-center gap-2 rounded-md py-2 pr-8 pl-2 text-sm outline-none select-none",
                    "data-highlighted:bg-muted data-selected:font-medium",
                    "data-disabled:pointer-events-none data-disabled:opacity-50",
                  )}
                >
                  <SelectPrimitive.ItemText className="text-muted-foreground">
                    {emptyLabel ?? placeholder}
                  </SelectPrimitive.ItemText>
                  <SelectPrimitive.ItemIndicator className="absolute right-2 flex items-center">
                    <Check className="size-3.5" />
                  </SelectPrimitive.ItemIndicator>
                </SelectPrimitive.Item>
              ) : null}
              {options.map((option) => (
                <SelectPrimitive.Item
                  key={option.value}
                  value={option.value}
                  label={option.label}
                  disabled={option.disabled}
                  className={cn(
                    "relative flex cursor-pointer items-center gap-2 rounded-md py-2 pr-8 pl-2 text-sm outline-none select-none",
                    "data-highlighted:bg-muted data-selected:font-medium",
                    "data-disabled:pointer-events-none data-disabled:opacity-50",
                  )}
                >
                  <SelectPrimitive.ItemText>{option.label}</SelectPrimitive.ItemText>
                  <SelectPrimitive.ItemIndicator className="absolute right-2 flex items-center">
                    <Check className="size-3.5" />
                  </SelectPrimitive.ItemIndicator>
                </SelectPrimitive.Item>
              ))}
            </SelectPrimitive.List>
          </SelectPrimitive.Popup>
        </SelectPrimitive.Positioner>
      </SelectPrimitive.Portal>
    </SelectPrimitive.Root>
  );
}
