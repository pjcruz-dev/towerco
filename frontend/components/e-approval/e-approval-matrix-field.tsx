"use client";

import { Input } from "@/components/ui/input";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { cn } from "@/lib/utils";
import {
  parseMatrixFieldOptions,
  parseMatrixState,
  setMatrixCellValue,
  setMatrixNoteValue,
} from "@/modules/e-approval/field-matrix";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
};

export function EApprovalMatrixField({ field, value, onChange, disabled }: Props) {
  const options = parseMatrixFieldOptions(field);
  const state = parseMatrixState(value);
  const columnCount = Math.max(options.columns.length, 1);
  const showNotes = options.row_notes === true;
  const notesLabel = options.row_notes_label?.trim() || "Notes";

  return (
    <div className="overflow-x-auto">
      <div
        className="min-w-[16rem] grid items-center gap-x-3 gap-y-2.5"
        style={{
          gridTemplateColumns: showNotes
            ? `minmax(7rem, 12rem) repeat(${columnCount}, max-content) minmax(10rem, 1fr)`
            : `minmax(7rem, 14rem) repeat(${columnCount}, max-content)`,
        }}
      >
        <div />
        {options.columns.map((column) => (
          <div
            key={`hdr-${column.value}`}
            className="px-1 text-center text-xs font-medium text-muted-foreground"
          >
            {column.label}
          </div>
        ))}
        {showNotes ? (
          <div className="px-1 text-xs font-medium text-muted-foreground">{notesLabel}</div>
        ) : null}

        {options.rows.map((row) => {
          const answer = state[row.value];
          const selected = answer?.value ?? "";

          return (
            <RadioGroup
              key={row.value}
              value={selected || null}
              onValueChange={(next) => onChange(setMatrixCellValue(value, row.value, String(next ?? "")))}
              disabled={disabled}
              className="contents"
            >
              <div className="min-w-0 text-sm leading-snug text-foreground">{row.label}</div>
              {options.columns.map((column) => (
                <label
                  key={`${row.value}-${column.value}`}
                  className={cn(
                    "inline-flex cursor-pointer items-center justify-self-center p-1 text-sm",
                    disabled && "cursor-not-allowed opacity-60",
                  )}
                  title={`${row.label}: ${column.label}`}
                >
                  <RadioGroupItem value={column.value} aria-label={`${row.label}: ${column.label}`} />
                </label>
              ))}
              {showNotes ? (
                <Input
                  type="text"
                  disabled={disabled || selected === ""}
                  value={answer?.note ?? ""}
                  onChange={(e) => onChange(setMatrixNoteValue(value, row.value, e.target.value))}
                  placeholder={notesLabel}
                  className="h-8 min-w-0"
                  aria-label={`${row.label} ${notesLabel}`}
                />
              ) : null}
            </RadioGroup>
          );
        })}
      </div>
    </div>
  );
}
