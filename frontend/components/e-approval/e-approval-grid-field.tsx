"use client";

import { Copy, Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
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
import {
  applyGridRowAmountFormula,
  formatGridCurrencyDisplay,
  isHighlightedTotalColumn,
  leadingNonSummableColSpan,
  sumGridColumnValues,
  summableGridColumnIndexes,
} from "@/modules/e-approval/grid-row-formulas";
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
  // Wide expense grids stay dense even in comfortable compose dialogs.
  const comfortable = false;
  const touchComfortable = density === "comfortable";
  const summable = summableGridColumnIndexes(columnDefs);
  const showTotalsFooter = summable.some(Boolean) && grid.rows.length > 0;
  const labelColSpan = leadingNonSummableColSpan(summable);

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

  return (
    <div className={cn("w-full min-w-0 space-y-1.5", touchComfortable && "-mx-1 sm:mx-0")}>
      <div className="w-full overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
        <table className="w-full min-w-[720px] border-collapse text-[11px] leading-tight">
          <thead>
            <tr className="border-b border-border bg-muted/80">
              {columnDefs.map((col) => (
                <th
                  key={`${col.label}-h`}
                  title={col.label}
                  className={cn(
                    "border border-border px-1.5 py-1.5 text-center text-[10px] font-medium text-foreground",
                    isHighlightedTotalColumn(col) && "bg-muted text-foreground",
                  )}
                >
                  <span className="line-clamp-2 break-words">{col.label}</span>
                </th>
              ))}
              <th
                className="w-9 shrink-0 border border-border px-0.5 py-1.5"
                aria-label="Row actions"
              />
            </tr>
          </thead>
          <tbody>
            {grid.rows.map((row, rowIndex) => (
              <tr
                key={rowIndex}
                className={cn(
                  "border-b border-border last:border-0",
                  rowIndex % 2 === 1 ? "bg-muted/30" : "bg-card",
                )}
              >
                {columnDefs.map((colDef, colIndex) => (
                  <td
                    key={`${rowIndex}-${colDef.label}-${colIndex}`}
                    className={cn(
                      "border border-border px-1 py-1 align-middle",
                      isHighlightedTotalColumn(colDef) && "bg-muted/50",
                    )}
                  >
                    <EApprovalGridCell
                      column={colDef}
                      disabled={disabled || isHighlightedTotalColumn(colDef)}
                      comfortable={comfortable}
                      allowRemoteLookups={allowRemoteLookups}
                      value={row[columnKey(colIndex, columns.length)] ?? ""}
                      onChange={(cellValue) => updateCell(rowIndex, colIndex, cellValue)}
                    />
                  </td>
                ))}
                <td className="border border-border px-0.5 py-1 align-middle">
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-7 w-7 text-muted-foreground"
                    disabled={disabled}
                    onClick={() => removeRow(rowIndex)}
                    aria-label="Remove row"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
          {showTotalsFooter ? (
            <tfoot>
              <tr className="border-t border-border">
                <td
                  colSpan={labelColSpan}
                  className="border border-border bg-muted px-2 py-1.5 text-center text-[10px] font-medium tracking-wide text-foreground uppercase"
                >
                  Total expenses
                </td>
                {columnDefs.map((colDef, colIndex) => {
                  if (colIndex < labelColSpan) {
                    return null;
                  }
                  if (!summable[colIndex]) {
                    return (
                      <td
                        key={`foot-${colIndex}`}
                        className="border border-border bg-card px-1.5 py-1.5"
                      />
                    );
                  }
                  const total = sumGridColumnValues(grid.rows, colIndex, columns.length);
                  return (
                    <td
                      key={`foot-${colIndex}`}
                      className={cn(
                        "border border-border px-1.5 py-1.5 text-right text-[11px] font-medium tabular-nums text-foreground",
                        isHighlightedTotalColumn(colDef)
                          ? "bg-muted/80"
                          : "bg-muted/40",
                      )}
                    >
                      {formatGridCurrencyDisplay(String(total))}
                    </td>
                  );
                })}
                <td className="border border-border bg-muted/40" />
              </tr>
            </tfoot>
          ) : null}
        </table>
        <div className="flex flex-wrap items-center gap-1.5 border-t border-border px-2 py-1.5">
          <Button
            type="button"
            size="sm"
            variant="outline"
            className="h-7 px-2 text-xs"
            disabled={disabled}
            onClick={addRow}
          >
            <Plus className="mr-1 h-3.5 w-3.5" />
            Add row
          </Button>
          <Button
            type="button"
            size="sm"
            variant="outline"
            className="h-7 px-2 text-xs"
            disabled={disabled || grid.rows.length === 0}
            onClick={duplicatePreviousRow}
          >
            <Copy className="mr-1 h-3.5 w-3.5" />
            Duplicate previous row
          </Button>
        </div>
      </div>
    </div>
  );
}
