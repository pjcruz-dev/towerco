"use client";

import { EApprovalGridCell } from "@/components/e-approval/e-approval-grid-cell";
import { Checkbox } from "@/components/ui/checkbox";
import { cn } from "@/lib/utils";
import {
  checklistMatrixColumnAsGridDef,
  parseChecklistMatrixFieldOptions,
  parseChecklistMatrixState,
  setChecklistMatrixCellValue,
  setChecklistMatrixRowSelected,
} from "@/modules/e-approval/field-checklist-matrix";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
};

export function EApprovalChecklistMatrixField({ field, value, onChange, disabled }: Props) {
  const options = parseChecklistMatrixFieldOptions(field);
  const state = parseChecklistMatrixState(value, options.columns);
  const columnCount = Math.max(options.columns.length, 1);

  return (
    <div className="overflow-x-auto rounded-xl border border-border">
      <div
        className="min-w-[28rem] grid items-stretch"
        style={{
          gridTemplateColumns: `minmax(11rem, 16rem) repeat(${columnCount}, minmax(8rem, 1fr))`,
        }}
      >
        <div className="bg-slate-700 px-3 py-2 text-xs font-medium text-white dark:bg-slate-800">
          {options.row_select_label || "Cost Application"}
        </div>
        {options.columns.map((column) => (
          <div
            key={`hdr-${column.value}`}
            className="bg-slate-700 px-3 py-2 text-xs font-medium text-white dark:bg-slate-800"
          >
            {column.label}
          </div>
        ))}

        {options.rows.map((row, rowIndex) => {
          const answer = state[row.value] ?? { selected: false, cells: {} };
          const selected = answer.selected === true;
          const zebra = rowIndex % 2 === 1;

          return (
            <div key={row.value} className="contents">
              <label
                className={cn(
                  "flex min-w-0 items-center gap-2 border-t border-border px-3 py-2 text-sm",
                  zebra ? "bg-muted/20" : "bg-card",
                  disabled && "cursor-not-allowed opacity-60",
                )}
              >
                <Checkbox
                  checked={selected}
                  disabled={disabled}
                  onCheckedChange={(next) =>
                    onChange(
                      setChecklistMatrixRowSelected(
                        value,
                        row.value,
                        next === true,
                        options.columns,
                      ),
                    )
                  }
                  aria-label={`Select ${row.label}`}
                />
                <span className="min-w-0 leading-snug text-foreground">{row.label}</span>
              </label>

              {options.columns.map((column) => (
                <div
                  key={`${row.value}-${column.value}`}
                  className={cn(
                    "border-t border-l border-border px-2 py-1.5",
                    zebra ? "bg-muted/20" : "bg-card",
                  )}
                  onFocusCapture={() => {
                    if (!selected && !disabled) {
                      onChange(
                        setChecklistMatrixRowSelected(value, row.value, true, options.columns),
                      );
                    }
                  }}
                >
                  <EApprovalGridCell
                    column={checklistMatrixColumnAsGridDef(column)}
                    value={answer.cells[column.value] ?? ""}
                    disabled={disabled}
                    onChange={(next) =>
                      onChange(
                        setChecklistMatrixCellValue(
                          value,
                          row.value,
                          column.value,
                          next,
                          options.columns,
                        ),
                      )
                    }
                  />
                </div>
              ))}
            </div>
          );
        })}
      </div>
    </div>
  );
}
