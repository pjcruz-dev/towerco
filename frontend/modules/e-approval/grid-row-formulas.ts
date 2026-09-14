import {
  columnKey,
  parseGridColumnDefs,
  parseGridValue,
  serializeGridValue,
  type GridColumnDef,
  type GridFieldValue,
} from "@/modules/e-approval/field-options";
import { parseSubmissionAmount } from "@/modules/e-approval/parent-submission-link";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function findColumnIndex(labels: string[], patterns: RegExp[]): number | null {
  const index = labels.findIndex((label) => patterns.some((pattern) => pattern.test(label.trim())));
  return index >= 0 ? index : null;
}

function formatAmount(value: number): string {
  if (!Number.isFinite(value)) {
    return "0.00";
  }

  return value.toFixed(2);
}

function isRowTotalLabel(label: string): boolean {
  return /^(total|line\s*total|amount)$/i.test(label.trim());
}

/** Sum currency columns (except Total/Amount) into the Total column per row. */
export function applyExpenseRowTotalFormula(
  field: EApprovalFormFieldInput,
  gridRaw: string,
): string {
  const columns = parseGridColumnDefs(field);
  if (columns.length === 0) {
    return gridRaw;
  }

  const totalIndex = columns.findIndex((column) => isRowTotalLabel(column.label));
  if (totalIndex < 0) {
    return gridRaw;
  }

  const moneyIndexes = columns
    .map((column, index) => ({ column, index }))
    .filter(
      ({ column, index }) =>
        index !== totalIndex &&
        (column.type === "currency" || column.type === "number") &&
        !isRowTotalLabel(column.label),
    )
    .map(({ index }) => index);

  if (moneyIndexes.length === 0) {
    return gridRaw;
  }

  const grid = parseGridValue(gridRaw, columns.length);
  const nextRows = grid.rows.map((row) => {
    let sum = 0;
    for (const index of moneyIndexes) {
      sum += parseSubmissionAmount(row[columnKey(index, columns.length)] ?? "") ?? 0;
    }

    return {
      ...row,
      [columnKey(totalIndex, columns.length)]: formatAmount(sum),
    };
  });

  const next: GridFieldValue = { rows: nextRows };
  return serializeGridValue(next);
}

/** When a grid has Qty, Unit price, Discount, and Amount — keep Amount in sync. */
export function applyGridRowAmountFormula(
  field: EApprovalFormFieldInput,
  gridRaw: string,
): string {
  const columns = parseGridColumnDefs(field);
  const labels = columns.map((column) => column.label);
  const qtyIndex = findColumnIndex(labels, [/^qty$/i, /^quantity$/i]);
  const unitIndex = findColumnIndex(labels, [/unit\s*price/i, /^rate$/i, /^price$/i]);
  const discountIndex = findColumnIndex(labels, [/^discount$/i]);
  const amountIndex = findColumnIndex(labels, [/^amount$/i, /^line total$/i]);

  if (qtyIndex === null || unitIndex === null || amountIndex === null) {
    return applyExpenseRowTotalFormula(field, gridRaw);
  }

  const grid = parseGridValue(gridRaw, columns.length);
  const nextRows = grid.rows.map((row) => {
    const qty = parseSubmissionAmount(row[columnKey(qtyIndex, columns.length)] ?? "") ?? 0;
    const unit = parseSubmissionAmount(row[columnKey(unitIndex, columns.length)] ?? "") ?? 0;
    const discount =
      discountIndex === null
        ? 0
        : parseSubmissionAmount(row[columnKey(discountIndex, columns.length)] ?? "") ?? 0;
    const amount = Math.max(0, qty * unit - discount);

    return {
      ...row,
      [columnKey(amountIndex, columns.length)]: formatAmount(amount),
    };
  });

  const next: GridFieldValue = { rows: nextRows };
  return serializeGridValue(next);
}

export function applyGridRowFormulasToValues(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): Record<string, string> {
  const next = { ...values };

  for (const field of fields) {
    if (field.type !== "grid") {
      continue;
    }

    const raw = next[field.name] ?? "";
    if (!raw.trim()) {
      continue;
    }

    const patched = applyGridRowAmountFormula(field, raw);
    if (patched !== raw) {
      next[field.name] = patched;
    }
  }

  return next;
}

export function formatGridCurrencyDisplay(value: string): string {
  const parsed = parseSubmissionAmount(value);
  if (parsed === null) {
    const trimmed = value.trim();
    return trimmed !== "" ? trimmed : "₱0.00";
  }

  return `₱${parsed.toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

export function isMoneyGridColumn(column: GridColumnDef): boolean {
  return column.type === "currency" || column.type === "number";
}

export function isHighlightedTotalColumn(column: GridColumnDef): boolean {
  return isRowTotalLabel(column.label) || column.type === "currency" && /^total$/i.test(column.label.trim());
}

/** Column indexes that should show footer sums (money columns). */
export function summableGridColumnIndexes(columns: GridColumnDef[]): boolean[] {
  return columns.map((column) => isMoneyGridColumn(column));
}

export function leadingNonSummableColSpan(summable: boolean[]): number {
  let span = 0;
  for (const flag of summable) {
    if (flag) break;
    span += 1;
  }
  return Math.max(span, 1);
}

export function sumGridColumnValues(
  rows: Array<Record<string, string>>,
  colIndex: number,
  columnCount: number,
): number {
  return rows.reduce((sum, row) => {
    const parsed = parseSubmissionAmount(row[columnKey(colIndex, columnCount)] ?? "");
    return parsed === null ? sum : sum + parsed;
  }, 0);
}
