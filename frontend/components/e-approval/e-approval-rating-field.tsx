"use client";

import { Star } from "lucide-react";

import { parseRatingMaxStars } from "@/modules/e-approval/field-type-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  field: EApprovalFormFieldInput;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
};

export function EApprovalRatingField({ field, value, onChange, disabled }: Props) {
  const max = parseRatingMaxStars(field);
  const selected = Number(value) || 0;

  return (
    <div className="flex flex-wrap items-center gap-1" role="radiogroup" aria-label={field.label}>
      {Array.from({ length: max }, (_, i) => {
        const star = i + 1;
        const active = star <= selected;

        return (
          <button
            key={star}
            type="button"
            disabled={disabled}
            className={cn(
              "rounded-md p-1 transition-colors",
              active ? "text-amber-500" : "text-muted-foreground hover:text-amber-400",
              disabled && "cursor-not-allowed opacity-50",
            )}
            aria-label={`${star} of ${max}`}
            aria-checked={active}
            onClick={() => onChange(String(star))}
          >
            <Star className={cn("h-6 w-6", active && "fill-current")} />
          </button>
        );
      })}
      {selected > 0 ? (
        <button
          type="button"
          className="ml-2 text-xs text-muted-foreground hover:text-foreground"
          disabled={disabled}
          onClick={() => onChange("")}
        >
          Clear
        </button>
      ) : null}
    </div>
  );
}
