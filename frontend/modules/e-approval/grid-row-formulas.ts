import {
  columnKey,
  parseGridColumnDefs,
  parseGridValue,
  serializeGridValue,
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
    return gridRaw;
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
