"use client";

import { Calculator } from "lucide-react";

import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import {
  computedTotalHelpText,
  gridCurrencyColumns,
  gridNumberColumns,
  listGridFields,
  parseFieldComputedOptionsState,
  patchFieldComputedOptions,
  suggestComputedColumns,
  type ComputedOperationMode,
  type FieldComputedOptionsState,
} from "@/modules/e-approval/field-computed-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  allFields: EApprovalFormFieldInput[];
  fieldIndex: number;
  onChange: (options: Record<string, unknown>) => void;
  onValidationChange?: (patch: { help_text?: string }) => void;
};

export function EApprovalFieldComputedOptionsEditor({
  field,
  allFields,
  fieldIndex,
  onChange,
  onValidationChange,
}: Props) {
  const gridFields = listGridFields(allFields.filter((_, index) => index !== fieldIndex));
  const state = parseFieldComputedOptionsState(field, allFields);
  const selectedGrid = gridFields.find((entry) => entry.name === state.sourceField);

  const applyState = (next: FieldComputedOptionsState) => {
    onChange(patchFieldComputedOptions(field, next));
    if (next.enabled && next.mode !== "none") {
      onValidationChange?.({ help_text: computedTotalHelpText(next.mode) });
    }
  };

  const handleToggle = (enabled: boolean) => {
    if (!enabled) {
      applyState({ ...state, enabled: false, mode: "none", fromConvention: false });
      return;
    }

    const firstGrid = gridFields[0];
    const sourceField = state.sourceField || firstGrid?.name || "";
    const grid = gridFields.find((entry) => entry.name === sourceField) ?? firstGrid;
    const mode: ComputedOperationMode =
      state.mode !== "none" ? state.mode : gridNumberColumns(grid).length >= 2 ? "sum_grid_lines" : "sum_grid_column";
    const suggested = suggestComputedColumns(grid, mode);

    applyState({
      enabled: true,
      mode,
      sourceField,
      column: suggested.column,
      quantityColumn: suggested.quantityColumn,
      amountColumn: suggested.amountColumn,
      fromConvention: false,
    });
  };

  const handleModeChange = (mode: ComputedOperationMode) => {
    const grid = gridFields.find((entry) => entry.name === state.sourceField);
    const suggested = suggestComputedColumns(grid, mode);
    applyState({
      ...state,
      enabled: true,
      mode,
      fromConvention: false,
      ...suggested,
    });
  };

  const handleSourceChange = (sourceField: string) => {
    const grid = gridFields.find((entry) => entry.name === sourceField);
    const suggested = suggestComputedColumns(grid, state.mode === "none" ? "sum_grid_column" : state.mode);
    applyState({
      ...state,
      enabled: true,
      mode: state.mode === "none" ? "sum_grid_column" : state.mode,
      sourceField,
      fromConvention: false,
      ...suggested,
    });
  };

  const currencyColumns = gridCurrencyColumns(selectedGrid);
  const numericColumns = gridNumberColumns(selectedGrid);

  return (
    <div className="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3">
      <div className="flex items-start gap-2">
        <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
          <Calculator className="h-3.5 w-3.5" />
        </span>
        <div className="min-w-0 flex-1 space-y-1">
          <p className="text-xs font-medium text-foreground">Auto-calculated total</p>
          <p className="text-[11px] leading-relaxed text-muted-foreground">
            Sum values from a grid field. Place this total field above the grid for the best requestor experience.
          </p>
        </div>
      </div>

      <label className="flex items-center gap-2 text-sm">
        <Checkbox
          checked={state.enabled}
          onCheckedChange={(v) => handleToggle(v === true)}
          className="size-4"
        />
        Calculate automatically from a grid
      </label>

      {state.fromConvention && state.enabled ? (
        <p className="rounded-md border border-primary/20 bg-primary/5 px-2 py-1.5 text-[11px] text-muted-foreground">
          Using a built-in naming shortcut for this field. Customize below to use your own grid and columns.
        </p>
      ) : null}

      {state.enabled ? (
        <div className="space-y-3 border-t border-border/50 pt-3">
          {gridFields.length === 0 ? (
            <p className="text-xs text-amber-800 dark:text-amber-200">
              Add a grid field first, or drag <span className="font-medium">Total + expense lines</span> from the field
              catalog.
            </p>
          ) : (
            <>
              <div className="space-y-1">
                <Label htmlFor="ea-computed-source">Source grid</Label>
                <Select
                  id="ea-computed-source"
                  value={state.sourceField}
                  onChange={(e) => handleSourceChange(e.target.value)}
                >
                  <option value="">Select grid…</option>
                  {gridFields.map((grid) => (
                    <option key={grid.name} value={grid.name}>
                      {grid.label || grid.name}
                    </option>
                  ))}
                </Select>
              </div>

              <div className="space-y-1">
                <Label htmlFor="ea-computed-mode">Calculation</Label>
                <Select
                  id="ea-computed-mode"
                  value={state.mode}
                  onChange={(e) => handleModeChange(e.target.value as ComputedOperationMode)}
                >
                  <option value="sum_grid_column">Sum one column (expense / reimbursement lines)</option>
                  <option value="sum_grid_lines">Qty × unit price per row (purchase lines)</option>
                </Select>
              </div>

              {state.mode === "sum_grid_column" ? (
                <div className="space-y-1">
                  <Label htmlFor="ea-computed-column">Amount column</Label>
                  <Select
                    id="ea-computed-column"
                    value={state.column}
                    onChange={(e) =>
                      applyState({ ...state, column: e.target.value, enabled: true, fromConvention: false })
                    }
                  >
                    {(currencyColumns.length > 0 ? currencyColumns : ["Amount"]).map((label) => (
                      <option key={label} value={label}>
                        {label}
                      </option>
                    ))}
                  </Select>
                </div>
              ) : (
                <div className="grid gap-2 sm:grid-cols-2">
                  <div className="space-y-1">
                    <Label htmlFor="ea-computed-qty">Quantity column</Label>
                    <Select
                      id="ea-computed-qty"
                      value={state.quantityColumn}
                      onChange={(e) =>
                        applyState({
                          ...state,
                          quantityColumn: e.target.value,
                          enabled: true,
                          fromConvention: false,
                        })
                      }
                    >
                      {(numericColumns.length > 0 ? numericColumns : ["Qty"]).map((label) => (
                        <option key={label} value={label}>
                          {label}
                        </option>
                      ))}
                    </Select>
                  </div>
                  <div className="space-y-1">
                    <Label htmlFor="ea-computed-unit">Unit price column</Label>
                    <Select
                      id="ea-computed-unit"
                      value={state.amountColumn}
                      onChange={(e) =>
                        applyState({
                          ...state,
                          amountColumn: e.target.value,
                          enabled: true,
                          fromConvention: false,
                        })
                      }
                    >
                      {(currencyColumns.length > 0 ? currencyColumns : ["Unit price"]).map((label) => (
                        <option key={label} value={label}>
                          {label}
                        </option>
                      ))}
                    </Select>
                  </div>
                </div>
              )}

              <p className="text-[11px] text-muted-foreground">{computedTotalHelpText(state.mode)}</p>
            </>
          )}
        </div>
      ) : null}
    </div>
  );
}
