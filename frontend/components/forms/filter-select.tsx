"use client";

import type { ReactNode } from "react";

import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { filterSelectClassName, touchFilterSelectClassName } from "@/lib/ui/field-control";
import { cn } from "@/lib/utils";

type FilterSelectProps = {
  id: string;
  label?: ReactNode;
  value: string;
  onChange: (value: string) => void;
  children: ReactNode;
  className?: string;
  selectClassName?: string;
  hideLabel?: boolean;
  touchFriendly?: boolean;
};

export function FilterSelect({
  id,
  label,
  value,
  onChange,
  children,
  className,
  selectClassName,
  hideLabel = false,
  touchFriendly = false,
}: FilterSelectProps) {
  return (
    <div className={cn("min-w-0 space-y-1.5", className)}>
      {label && !hideLabel ? (
        <Label htmlFor={id} className="text-xs font-medium text-muted-foreground">
          {label}
        </Label>
      ) : null}
      <Select
        id={id}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className={cn(
          touchFriendly ? touchFilterSelectClassName : filterSelectClassName,
          selectClassName,
        )}
      >
        {children}
      </Select>
    </div>
  );
}
