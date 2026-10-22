"use client";

import { forwardRef } from "react";
import type { FieldError } from "react-hook-form";
import type { ChangeEvent, InputHTMLAttributes, ReactNode } from "react";

import { DatePicker } from "@/components/ui/date-picker";
import { DateTimePicker } from "@/components/ui/date-time-picker";

type FormInputProps = {
  label: ReactNode;
  error?: FieldError;
  touchFriendly?: boolean;
  /** Icon shown inside the field, before the text. */
  icon?: ReactNode;
  /** Renders the shared DatePicker (ISO yyyy-mm-dd). */
  date?: boolean;
  /** Renders DatePicker + time (datetime-local shape: yyyy-MM-ddTHH:mm). */
  dateTime?: boolean;
} & Omit<InputHTMLAttributes<HTMLInputElement>, "type"> & {
  type?: Exclude<InputHTMLAttributes<HTMLInputElement>["type"], "date" | "datetime-local">;
};

function synthesizeChangeEvent(value: string): ChangeEvent<HTMLInputElement> {
  return {
    target: { value },
    currentTarget: { value },
  } as ChangeEvent<HTMLInputElement>;
}

export const FormInput = forwardRef<HTMLInputElement, FormInputProps>(function FormInput(
  {
    label,
    error,
    touchFriendly = false,
    icon,
    className = "",
    date = false,
    dateTime = false,
    type,
    value,
    onChange,
    disabled,
    id,
    readOnly,
    placeholder,
    "aria-invalid": ariaInvalid,
    ...props
  },
  ref,
) {
  if (date || dateTime) {
    const dateValue = value == null ? "" : String(value);
    const invalid = ariaInvalid === true || ariaInvalid === "true";
    return (
      <label className="space-y-1.5">
        <span className="text-sm font-medium">{label}</span>
        {dateTime ? (
          <DateTimePicker
            id={id}
            value={dateValue}
            onChange={(next) => onChange?.(synthesizeChangeEvent(next))}
            disabled={disabled}
            readOnly={readOnly}
            aria-invalid={invalid}
            className={className}
          />
        ) : (
          <DatePicker
            id={id}
            value={dateValue}
            onChange={(next) => onChange?.(synthesizeChangeEvent(next))}
            disabled={disabled}
            readOnly={readOnly}
            placeholder={placeholder}
            aria-invalid={invalid}
            className={`${touchFriendly ? "min-h-11 text-base" : "h-10"} ${className}`.trim()}
          />
        )}
        {error ? <span className="text-xs text-destructive">{error.message}</span> : null}
      </label>
    );
  }

  return (
    <label className="space-y-1.5">
      <span className="text-sm font-medium">{label}</span>
      <span className="relative block">
        {icon ? (
          <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted-foreground [&_svg]:size-4">
            {icon}
          </span>
        ) : null}
        <input
          ref={ref}
          type={type}
          value={value}
          onChange={onChange}
          disabled={disabled}
          id={id}
          readOnly={readOnly}
          placeholder={placeholder}
          aria-invalid={ariaInvalid}
          className={`w-full rounded-md border bg-background text-sm outline-none ring-0 focus:border-ring ${
            icon ? "pr-3 pl-9" : "px-3"
          } ${touchFriendly ? "min-h-11 text-base" : "h-10"} ${className}`}
          {...props}
        />
      </span>
      {error ? <span className="text-xs text-destructive">{error.message}</span> : null}
    </label>
  );
});
