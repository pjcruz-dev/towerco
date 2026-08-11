"use client";

import { useId } from "react";

import { Input } from "@/components/ui/input";

export function RegistryListToolbar({
  label,
  value,
  onChange,
  placeholder = "Search…",
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
}) {
  const inputId = useId();

  return (
    <div className="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
      <div className="min-w-0 flex-1">
        <label className="mb-1 block text-xs font-medium text-muted-foreground" htmlFor={inputId}>
          {label}
        </label>
        <Input
          id={inputId}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          className="h-11 w-full text-base sm:h-9 sm:max-w-md sm:text-sm"
        />
      </div>
    </div>
  );
}
