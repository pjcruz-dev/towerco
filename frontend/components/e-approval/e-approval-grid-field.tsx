"use client";

import { Copy, Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import { EApprovalGridCell } from "@/components/e-approval/e-approval-grid-cell";
import {
  columnKey,
  emptyGridValue,
  parseGridColumnDefs,
  parseGridValue,
  serializeGridValue,
  type GridColumnDef,
  type GridFieldValue,
} from "@/modules/e-approval/field-options";
import { applyGridRowAmountFormula } from "@/modules/e-approval/grid-row-formulas";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  /** @deprecated Use field — column definitions include cell types. */
  columns?: string[];
  field?: EApprovalFormFieldInput;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  /** Larger inputs and spacing for requestor submit dialogs. */
  density?: "compact" | "comfortable";
  allowRemoteLookups?: boolean;
};

export function EApprovalGridField({
  columns: columnsLegacy,
  field,
  value,
  onChange,
  disabled,
  density = "compact",
  allowRemoteLookups = true,
}: Props) {
  const columnDefs: GridColumnDef[] = field
    ? parseGridColumnDefs(field)
    : (columnsLegacy ?? []).map((label) => ({ label, type: "text" as const }));

  const columns = columnDefs.map((c) => c.label);
  const grid = parseGridValue(value, columns.length);
  const comfortable = density === "comfortable";

  const commit = (next: GridFieldValue) => {
    onChange(serializeGridValue(next));
  };

  const updateCell = (rowIndex: number, colIndex: number, cellValue: string) => {
    const rows = grid.rows.map((row, i) =>
      i === rowIndex ? { ...row, [columnKey(colIndex, columns.length)]: cellValue } : row,
    );
    let next: GridFieldValue = { rows };
    if (field) {
      const serialized = serializeGridValue(next);
      const withFormulas = applyGridRowAmountFormula(field, serialized);
      next = parseGridValue(withFormulas, columns.length);
    }
    commit(next);
  };

  const addRow = () => {
    commit({ rows: [...grid.rows, emptyGridValue(columns.length).rows[0] ?? {}] });
  };

  const duplicatePreviousRow = () => {
    if (grid.rows.length === 0) {
      addRow();
      return;
    }

    const previous = grid.rows[grid.rows.length - 1] ?? {};
    const copy: Record<string, string> = {};
    for (let i = 0; i < columns.length; i += 1) {
      const key = columnKey(i, columns.length);
      copy[key] = previous[key] ?? "";
    }

    commit({ rows: [...grid.rows, copy] });
  };

  const removeRow = (rowIndex: number) => {
    if (grid.rows.length <= 1) {
      commit(emptyGridValue(columns.length));
      return;
    }
    commit({ rows: grid.rows.filter((_, i) => i !== rowIndex) });
  };

  if (columns.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">
        This grid has no columns configured. Ask an administrator to update the form definition.
      </p>
    );
  }

  const columnWidth =
    columns.length > 0
      ? `${Math.max(100 / columns.length, 8)}%`
      : undefined;

  return (
    <div className={cn("w-full min-w-0 space-y-2", comfortable && "-mx-1 sm:mx-0")}>
      <div className="w-full overflow-x-auto rounded-lg border border-border bg-card shadow-sm">
        <table
          className={cn(
            "w-full border-collapse",
            comfortable ? "min-w-full text-sm" : "min-w-[520px] text-sm",
          )}
          style={{ tableLayout: columns.length <= 4 ? "fixed" : "auto" }}
        >
          <thead>
            <tr className="border-b border-border bg-muted/50">
              {columns.map((col) => (
                <th
                  key={col}
                  title={col}
                  style={columnWidth ? { width: columnWidth, minWidth: comfortable ? 120 : 96 } : undefined}
                  className={cn(
                    "px-2 text-left font-medium text-muted-foreground",
                    comfortable ? "py-2.5 text-xs sm:text-sm" : "py-2 text-xs",
                  )}
                >
                  <span className="line-clamp-2 break-words">{col}</span>
                </th>
              ))}
              <th
                className={cn("w-11 shrink-0 px-1", comfortable ? "py-2.5" : "py-2")}
                aria-label="Row actions"
              />
            </tr>
          </thead>
          <tbody>
            {grid.rows.map((row, rowIndex) => (
              <tr key={rowIndex} className="border-b border-border/60 last:border-0">
                {columnDefs.map((colDef, colIndex) => (
                  <td
                    key={`${rowIndex}-${colDef.label}-${colIndex}`}
                    className={cn("px-2 align-top", comfortable ? "py-2" : "py-1.5")}
                  >
                    <EApprovalGridCell
                      column={colDef}
                      disabled={disabled}
                      comfortable={comfortable}
                      allowRemoteLookups={allowRemoteLookups}
                      value={row[columnKey(colIndex, columns.length)] ?? ""}
                      onChange={(cellValue) => updateCell(rowIndex, colIndex, cellValue)}
                    />
                  </td>
                ))}
                <td className={cn("px-1 align-top", comfortable ? "py-2" : "py-1.5")}>
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className={cn("text-muted-foreground", comfortable ? "h-10 w-10" : "h-8 w-8")}
                    disabled={disabled}
                    onClick={() => removeRow(rowIndex)}
                    aria-label="Remove row"
                  >
                    <Trash2 className={cn(comfortable ? "h-4 w-4" : "h-3.5 w-3.5")} />
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        <div
          className={cn(
            "flex flex-wrap items-center gap-2 border-t border-border px-3",
            comfortable ? "py-3" : "py-2",
          )}
        >
          <Button type="button" size={comfortable ? "default" : "sm"} variant="outline" disabled={disabled} onClick={addRow}>
            <Plus className="mr-1.5 h-4 w-4" />
            Add row
          </Button>
          <Button
            type="button"
            size={comfortable ? "default" : "sm"}
            variant="outline"
            disabled={disabled || grid.rows.length === 0}
            onClick={duplicatePreviousRow}
          >
            <Copy className="mr-1.5 h-4 w-4" />
            Duplicate previous row
          </Button>
        </div>
      </div>
    </div>
  );
}
