import {
  resolveFieldComputedConfig,
  type FieldComputedOperation,
} from "@/modules/e-approval/field-computed";
import { fieldOptionsToRecord, mergeFieldOptions, parseGridColumnDefs } from "@/modules/e-approval/field-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type ComputedOperationMode = "none" | "sum_grid_column" | "sum_grid_lines";

export type FieldComputedOptionsState = {
  enabled: boolean;
  mode: ComputedOperationMode;
  sourceField: string;
  column: string;
  quantityColumn: string;
  amountColumn: string;
  fromConvention: boolean;
};

const DEFAULT_SUM_COLUMN = "Amount";
const DEFAULT_QTY_COLUMN = "Qty";
const DEFAULT_UNIT_COLUMN = "Unit price";

export function listGridFields(fields: EApprovalFormFieldInput[]): EApprovalFormFieldInput[] {
  return fields.filter((field) => field.type === "grid");
}

export function parseFieldComputedOptionsState(
  field: EApprovalFormFieldInput,
  allFields: EApprovalFormFieldInput[],
): FieldComputedOptionsState {
  const options = fieldOptionsToRecord(field);
  const explicit = options.computed_from;
  const resolved = resolveFieldComputedConfig(field, allFields);
  const fromConvention = resolved !== null && (!explicit || typeof explicit !== "object");

  if (!resolved) {
    return {
      enabled: false,
      mode: "none",
      sourceField: "",
      column: DEFAULT_SUM_COLUMN,
      quantityColumn: DEFAULT_QTY_COLUMN,
      amountColumn: DEFAULT_UNIT_COLUMN,
      fromConvention: false,
    };
  }

  if (resolved.operation === "sum_grid_column") {
    return {
      enabled: true,
      mode: "sum_grid_column",
      sourceField: resolved.source_field,
      column: resolved.column ?? DEFAULT_SUM_COLUMN,
      quantityColumn: DEFAULT_QTY_COLUMN,
      amountColumn: DEFAULT_UNIT_COLUMN,
      fromConvention,
    };
  }

  return {
    enabled: true,
    mode: "sum_grid_lines",
    sourceField: resolved.source_field,
    column: DEFAULT_SUM_COLUMN,
    quantityColumn: resolved.quantity_column ?? DEFAULT_QTY_COLUMN,
    amountColumn: resolved.amount_column ?? DEFAULT_UNIT_COLUMN,
    fromConvention,
  };
}

function gridColumnLabels(gridField: EApprovalFormFieldInput | undefined): string[] {
  if (!gridField) {
    return [];
  }

  return parseGridColumnDefs(gridField).map((column) => column.label).filter((label) => label.trim() !== "");
}

export function gridCurrencyColumns(gridField: EApprovalFormFieldInput | undefined): string[] {
  if (!gridField) {
    return [];
  }

  return parseGridColumnDefs(gridField)
    .filter((column) => column.type === "currency")
    .map((column) => column.label);
}

export function gridNumberColumns(gridField: EApprovalFormFieldInput | undefined): string[] {
  if (!gridField) {
    return [];
  }

  return parseGridColumnDefs(gridField)
    .filter((column) => column.type === "number" || column.type === "currency")
    .map((column) => column.label);
}

export function suggestComputedColumns(
  gridField: EApprovalFormFieldInput | undefined,
  mode: ComputedOperationMode,
): { column: string; quantityColumn: string; amountColumn: string } {
  const labels = gridColumnLabels(gridField);
  const currency = gridCurrencyColumns(gridField);
  const numeric = gridNumberColumns(gridField);

  const amount =
    currency.find((label) => label.toLowerCase().includes("amount")) ??
    currency[0] ??
    labels.find((label) => label.toLowerCase().includes("amount")) ??
    DEFAULT_SUM_COLUMN;

  const qty =
    numeric.find((label) => /qty|quantity/i.test(label)) ??
    numeric[0] ??
    DEFAULT_QTY_COLUMN;

  const unit =
    currency.find((label) => /unit|price|rate/i.test(label)) ??
    currency.find((label) => label !== amount) ??
    currency[0] ??
    DEFAULT_UNIT_COLUMN;

  if (mode === "sum_grid_lines") {
    return { column: amount, quantityColumn: qty, amountColumn: unit };
  }

  return { column: amount, quantityColumn: qty, amountColumn: unit };
}

function buildComputedFromConfig(state: FieldComputedOptionsState): FieldComputedOperation {
  if (state.mode === "sum_grid_lines") {
    return {
      operation: "sum_grid_lines",
      source_field: state.sourceField,
      quantity_column: state.quantityColumn,
      amount_column: state.amountColumn,
    };
  }

  return {
    operation: "sum_grid_column",
    source_field: state.sourceField,
    column: state.column,
  };
}

export function patchFieldComputedOptions(
  field: EApprovalFormFieldInput,
  state: FieldComputedOptionsState,
): Record<string, unknown> {
  const current = fieldOptionsToRecord(field);

  if (!state.enabled || state.mode === "none" || state.sourceField.trim() === "") {
    const next = { ...current };
    delete next.computed_from;
    delete next.read_only;
    return next;
  }

  return mergeFieldOptions(field, {
    read_only: true,
    computed_from: buildComputedFromConfig(state),
  });
}

export function fieldSupportsComputedTotal(field: EApprovalFormFieldInput): boolean {
  return field.type === "currency" || field.type === "number";
}

export function computedTotalHelpText(mode: ComputedOperationMode): string {
  if (mode === "sum_grid_lines") {
    return "Auto-calculated from quantity × unit price on each line.";
  }
  if (mode === "sum_grid_column") {
    return "Auto-calculated from line amounts.";
  }

  return "";
}
