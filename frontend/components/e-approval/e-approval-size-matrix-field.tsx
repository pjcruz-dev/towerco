"use client";

import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import {
  parseSizeMatrixRows,
  parseSizeMatrixValue,
  setSizeMatrixRowValue,
  sizeMatrixRowInput,
} from "@/modules/e-approval/field-size-matrix";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
};

export function EApprovalSizeMatrixField({ field, value, onChange, disabled }: Props) {
  const rows = parseSizeMatrixRows(field);
  const state = parseSizeMatrixValue(value);

  return (
    <div className="space-y-2.5">
      {rows.map((row) => {
        const entry = state[row.value] ?? {};
        const input = sizeMatrixRowInput(row);

        if (input === "text") {
          return (
            <div
              key={row.value}
              className="grid grid-cols-[minmax(7rem,12rem)_minmax(0,1fr)] items-center gap-x-3 gap-y-1.5 text-sm"
            >
              <div className="min-w-0 font-normal text-foreground">{row.label}</div>
              <Input
                type="text"
                disabled={disabled}
                value={entry.text ?? ""}
                onChange={(e) => onChange(setSizeMatrixRowValue(value, row.value, { text: e.target.value }))}
                className="h-8 min-w-0"
                aria-label={row.label}
              />
            </div>
          );
        }

        const na = entry.na === true;
        const sizeDisabled = disabled || na;

        return (
          <div
            key={row.value}
            className="grid grid-cols-[minmax(7rem,12rem)_auto] items-center gap-x-3 gap-y-1.5 text-sm"
          >
            <div className="min-w-0 font-normal text-foreground">{row.label}</div>
            <div className="inline-flex items-center gap-1.5 whitespace-nowrap">
              <span className="text-xs text-muted-foreground">size :</span>
              <Input
                type="number"
                inputMode="decimal"
                disabled={sizeDisabled}
                value={na ? "" : (entry.w ?? "")}
                onChange={(e) => onChange(setSizeMatrixRowValue(value, row.value, { w: e.target.value, na: false }))}
                className="h-8 w-16"
                aria-label={`${row.label} width`}
              />
              <span className="text-xs text-muted-foreground">x</span>
              <Input
                type="number"
                inputMode="decimal"
                disabled={sizeDisabled}
                value={na ? "" : (entry.h ?? "")}
                onChange={(e) => onChange(setSizeMatrixRowValue(value, row.value, { h: e.target.value, na: false }))}
                className="h-8 w-16"
                aria-label={`${row.label} height`}
              />
              <label
                className={cn(
                  "inline-flex items-center gap-1.5 text-sm",
                  disabled && "opacity-60",
                )}
              >
                <Checkbox
                  disabled={disabled}
                  checked={na}
                  onCheckedChange={(next) =>
                    onChange(
                      setSizeMatrixRowValue(
                        value,
                        row.value,
                        next === true ? { na: true } : { na: false, w: "", h: "" },
                      ),
                    )
                  }
                />
                <span className="text-xs text-muted-foreground">NA</span>
              </label>
            </div>
          </div>
        );
      })}
    </div>
  );
}
