import {
  columnKey,
  parseGridColumnDefs,
  parseGridValue,
  serializeGridValue,
  type GridColumnDef,
  type GridFieldValue,
  type SelectChoice,
} from "@/modules/e-approval/field-options";
import { calendarDate, isIsoDateString, parseDateRangeValue } from "@/modules/e-approval/field-type-options";
import { parseSubmissionAmount } from "@/modules/e-approval/parent-submission-link";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

function findColumnIndex(labels: string[], patterns: RegExp[]): number | null {
  const index = labels.findIndex((label) => patterns.some((pattern) => pattern.test(label.trim())));
  return index >= 0 ? index : null;
}

function roundMoney(value: number): number {
  if (!Number.isFinite(value)) {
    return 0;
  }

  return Math.round(value * 100) / 100;
}

function formatAmount(value: number): string {
  return roundMoney(value).toFixed(2);
}

function isRowTotalLabel(label: string): boolean {
  return /^(total|line\s*total)$/i.test(label.trim());
}

/** Sum other money columns into a Total column. Amount stays an input unless Quantity and Rate calculate it. */
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
  // Liquidation keeps Amount as the figure the requestor types. Per diem and lodging still fill it from the daily rate.
  if (labels.some((label) => /^forecast amount$/i.test(label.trim())) || expenseGridKeepsTypedAmount(columns)) {
    return gridRaw;
  }

  const grid = parseGridValue(gridRaw, columns.length);
  const nextRows = grid.rows.map((row) => {
    const qtyRaw = (row[columnKey(qtyIndex, columns.length)] ?? "").trim();
    const unitRaw = (row[columnKey(unitIndex, columns.length)] ?? "").trim();
    if (qtyRaw === "" && unitRaw === "") {
      return row;
    }
    const qty = parseSubmissionAmount(qtyRaw) ?? 0;
    const unit = parseSubmissionAmount(unitRaw) ?? 0;
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

  return applyExpenseVatSummary(fields, applyPerDiemActivityDays(fields, next));
}

type ActivityDateRange = { from: string; to: string };

function utcDayNumber(iso: string): number {
  const [year, month, day] = iso.split("-").map((part) => Number(part));
  return Date.UTC(year ?? 0, (month ?? 1) - 1, day ?? 1);
}

/** Dates strictly after `after` and strictly before `before`. Lodging uses this span. */
export function daysStrictlyBetween(after: string, before: string): number {
  const start = calendarDate(after);
  const end = calendarDate(before);
  if (!isIsoDateString(start) || !isIsoDateString(end)) {
    return 0;
  }
  const diff = Math.round((utcDayNumber(end) - utcDayNumber(start)) / 86_400_000);
  return Math.max(0, diff - 1);
}

/** Every calendar date on the line, including the first day and the last day. */
export function inclusiveDays(from: string, to: string): number | null {
  const start = calendarDate(from);
  const end = calendarDate(to);
  if (!isIsoDateString(start) || !isIsoDateString(end) || start > end) {
    return null;
  }
  const diff = Math.round((utcDayNumber(end) - utcDayNumber(start)) / 86_400_000);

  return diff + 1;
}

function dateRangeOnRow(
  columns: GridColumnDef[],
  row: Record<string, string>,
): { from: string; to: string } | null {
  const dateIndex = columns.findIndex(
    (column) =>
      column.type === "date_range" ||
      /^date$/i.test(column.label.trim()) ||
      /^from date\b/i.test(column.label.trim()),
  );
  if (dateIndex < 0) {
    return null;
  }
  const range = parseDateRangeValue(row[columnKey(dateIndex, columns.length)] ?? "");
  if (!range.from && !range.to) {
    return null;
  }

  return range;
}

/** Per diem days on this line. A one-day range counts as one day. */
export function perDiemDaysOnRow(columns: GridColumnDef[], row: Record<string, string>): number | null {
  const range = dateRangeOnRow(columns, row);
  if (!range) {
    return null;
  }

  return inclusiveDays(range.from, range.to);
}

/**
 * Lodging nights from the first date to the last date.
 * 14 Oct through 16 Oct is 2 nights. A single date is 1.
 */
export function lodgingNights(from: string, to: string): number | null {
  const start = calendarDate(from);
  const end = calendarDate(to);
  if (!isIsoDateString(start) || !isIsoDateString(end) || start > end) {
    return null;
  }
  const diff = Math.round((utcDayNumber(end) - utcDayNumber(start)) / 86_400_000);

  return Math.max(diff, 1);
}

export function lodgingNightsOnRow(columns: GridColumnDef[], row: Record<string, string>): number | null {
  const range = dateRangeOnRow(columns, row);
  if (!range) {
    return null;
  }

  return lodgingNights(range.from, range.to);
}

export function rowIsLodging(columns: GridColumnDef[], row: Record<string, string>): boolean {
  const typeIndex = columns.findIndex((column) => /^expense type$/i.test(column.label.trim()));
  if (typeIndex < 0) {
    return false;
  }
  const raw = (row[columnKey(typeIndex, columns.length)] ?? "").trim();
  if (raw === "") {
    return false;
  }
  const choice = expenseTypeChoice(columns, raw);
  if (choice?.data?.hotel === true) {
    return true;
  }
  const code = raw.toLowerCase();
  const label = (choice?.label ?? "").trim().toLowerCase();
  if (code === "lodging" || label === "lodging") {
    return true;
  }

  return /hotel\s*(&|and)\s*accommodat/i.test(raw) || /hotel\s*(&|and)\s*accommodat/i.test(choice?.label ?? "");
}

/**
 * Per diem days for one request.
 * The earliest date is the outbound day and the latest date is the return day.
 * Every calendar date strictly between those two counts once.
 * Returns null when no activity has a complete date range.
 */
export function perDiemDaysBetweenTrips(ranges: ActivityDateRange[]): number | null {
  const complete = ranges.flatMap((range) => {
    const from = calendarDate(range.from);
    const to = calendarDate(range.to);
    if (!isIsoDateString(from) || !isIsoDateString(to) || from > to) {
      return [];
    }
    return [{ from, to }];
  });
  if (complete.length === 0) {
    return null;
  }

  const outbound = complete.reduce((earliest, range) => (range.from < earliest ? range.from : earliest), complete[0]?.from ?? "");
  const inbound = complete.reduce((latest, range) => (range.to > latest ? range.to : latest), complete[0]?.to ?? "");
  if (!outbound || !inbound) {
    return null;
  }

  return daysStrictlyBetween(outbound, inbound);
}

/** Per diem days for the request, from the date ranges on the forecast lines. */
export function perDiemDaysForValues(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): number | null {
  return perDiemDaysBetweenTrips(activityDateRanges(fields, values));
}

/** Line pesos for a per diem rate. Each day on the line counts, including a one-day range. */
export function perDiemLineTotal(dailyRate: number, days: number | null): number {
  const rate = roundMoney(Math.max(0, dailyRate));
  if (rate <= 0 || days === null || days < 1) {
    return 0;
  }

  return roundMoney(rate * days);
}

/**
 * Money a line contributes.
 * On a forecast grid the Amount cell is the per diem daily rate.
 * On liquidation and reimbursement the Rate cell is that rate, and Amount is the line total.
 */
export function expenseLineMoney(columns: GridColumnDef[], row: Record<string, string>): number {
  const amountIndex = columns.findIndex((column) => /^amount$/i.test(column.label.trim()));
  const rateIndex = columns.findIndex((column) => /^rate$/i.test(column.label.trim()));
  const read = (index: number) =>
    index < 0 ? 0 : Math.max(0, parseSubmissionAmount(row[columnKey(index, columns.length)] ?? "") ?? 0);

  const typeIndex = columns.findIndex((column) => /^expense type$/i.test(column.label.trim()));
  const perDiem = typeIndex < 0 || expenseRowNeeds(columns, row).perDiem;
  const lodging = rowIsLodging(columns, row);
  if (expenseGridKeepsTypedAmount(columns) || (!perDiem && !lodging)) {
    return roundMoney(read(amountIndex >= 0 ? amountIndex : rateIndex));
  }

  const daily = rateIndex >= 0 ? read(rateIndex) : read(amountIndex);
  const days = perDiem ? perDiemDaysOnRow(columns, row) : lodgingNightsOnRow(columns, row);

  return perDiemLineTotal(daily, days);
}

function dateRangesOnGrid(field: EApprovalFormFieldInput, values: Record<string, string>): ActivityDateRange[] | null {
  const columns = parseGridColumnDefs(field);
  const dateIndex = columns.findIndex(
    (column) =>
      column.type === "date_range" ||
      /^date$/i.test(column.label.trim()) ||
      /^from date\b/i.test(column.label.trim()),
  );
  if (dateIndex < 0) {
    return null;
  }
  const raw = values[field.name] ?? "";
  if (!raw.trim()) {
    return [];
  }
  const grid = parseGridValue(raw, columns.length);

  return grid.rows.map((row) => parseDateRangeValue(row[columnKey(dateIndex, columns.length)] ?? ""));
}

function activityDateRanges(fields: EApprovalFormFieldInput[], values: Record<string, string>): ActivityDateRange[] {
  const expense = fields.find((field) => field.name === "expense_lines" && field.type === "grid");
  const expenseRanges = expense ? dateRangesOnGrid(expense, values) : null;
  if (expenseRanges) {
    return expenseRanges;
  }
  const activity = fields.find((field) => field.name === "activity_lines" && field.type === "grid");
  const activityRanges = activity ? dateRangesOnGrid(activity, values) : null;

  return activityRanges ?? [];
}

/**
 * Per diem uses the activity dates.
 * A grid with Rate stores the daily rate there and writes rate × days into Amount.
 * A forecast grid leaves Amount as the typed daily rate.
 * A leftover Days cell is cleared.
 */
export function applyPerDiemActivityDays(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): Record<string, string> {
  const gridField = fields.find((field) => field.name === "expense_lines" && field.type === "grid");
  if (!gridField) {
    return values;
  }
  const columns = parseGridColumnDefs(gridField);
  const labels = columns.map((column) => column.label);
  const daysIndex = findColumnIndex(labels, [/^days$/i]);
  const rateIndex = findColumnIndex(labels, [/^rate$/i]);
  const amountIndex = findColumnIndex(labels, [/^amount$/i]);
  if (daysIndex === null && (rateIndex === null || amountIndex === null)) {
    return values;
  }
  const raw = values[gridField.name] ?? "";
  if (!raw.trim()) {
    return values;
  }

  const grid = parseGridValue(raw, columns.length);
  let changed = false;
  const rows = grid.rows.map((row) => {
    const next = { ...row };
    if (daysIndex !== null) {
      const key = columnKey(daysIndex, columns.length);
      if ((next[key] ?? "") !== "") {
        next[key] = "";
        changed = true;
      }
    }
    if (
      !expenseGridKeepsTypedAmount(columns) &&
      rateIndex !== null &&
      amountIndex !== null &&
      (expenseRowNeeds(columns, row).perDiem || rowIsLodging(columns, row))
    ) {
      const key = columnKey(amountIndex, columns.length);
      const line = formatAmount(expenseLineMoney(columns, row));
      if ((next[key] ?? "") !== line) {
        next[key] = line;
        changed = true;
      }
    }
    return next;
  });
  if (!changed) {
    return values;
  }

  return { ...values, [gridField.name]: serializeGridValue({ rows }) };
}

/** 12% inside the amount. Matches the E-Forms setting when it is left at the default. */
const DEFAULT_VAT_PERCENT = 12;

/** Per-line split used by the form totals and the review columns. */
export function expenseLineVatParts(
  columns: GridColumnDef[],
  row: Record<string, string>,
): { vat: number; nonVatable: number; vatableNet: number } | null {
  const hasVatSplit = columns.some((column) => /^(expense type|vat treatment)$/i.test(column.label.trim()));
  const hasMoney = columns.some((column) => /^(amount|rate)$/i.test(column.label.trim()));
  if (!hasVatSplit || !hasMoney) {
    return null;
  }

  const amount = expenseLineMoney(columns, row);
  const vatable = expenseRowNeeds(columns, row).vatableAmount && DEFAULT_VAT_PERCENT > 0;
  const vat = vatable ? roundMoney((amount * DEFAULT_VAT_PERCENT) / (100 + DEFAULT_VAT_PERCENT)) : 0;

  if (vatable) {
    return { vat, nonVatable: 0, vatableNet: roundMoney(amount - vat) };
  }

  return { vat, nonVatable: amount, vatableNet: 0 };
}

/**
 * VAT on each expense line, plus the form totals beside the grid.
 * Amount already includes VAT. Non-VATable lines, including Per Diem, contribute no VAT.
 */
export function applyExpenseVatSummary(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): Record<string, string> {
  const gridField = fields.find((field) => field.name === "expense_lines" && field.type === "grid");
  if (!gridField) {
    return values;
  }

  const columns = parseGridColumnDefs(gridField);
  const labels = columns.map((column) => column.label);
  const hasVatSplit = labels.some((label) => /^(expense type|vat treatment)$/i.test(label.trim()));
  if (!hasVatSplit) {
    return values;
  }
  const amountIndex = findColumnIndex(labels, [/^amount$/i]) ?? findColumnIndex(labels, [/^rate$/i]);
  const vatIndex = findColumnIndex(labels, [/^vat$/i]);
  if (amountIndex === null) {
    return values;
  }

  const raw = values[gridField.name] ?? "";
  const grid = raw.trim() ? parseGridValue(raw, columns.length) : { rows: [] };
  let vatTotal = 0;
  let nonVatable = 0;
  let vatableNet = 0;

  const rows = grid.rows.map((row) => {
    const parts = expenseLineVatParts(columns, row);
    const vat = parts?.vat ?? 0;
    nonVatable += parts?.nonVatable ?? 0;
    vatableNet += parts?.vatableNet ?? 0;
    vatTotal += vat;
    if (vatIndex === null) {
      return row;
    }
    return {
      ...row,
      [columnKey(vatIndex, columns.length)]: formatAmount(vat),
    };
  });

  const next = { ...values };
  if (raw.trim()) {
    next[gridField.name] = serializeGridValue({ rows });
  }
  const totals: Record<string, number> = {
    vat_total: vatTotal,
    non_vatable_total: nonVatable,
    vatable_net: vatableNet,
  };
  for (const [name, total] of Object.entries(totals)) {
    if (fields.some((field) => field.name === name)) {
      next[name] = formatAmount(total);
    }
  }

  return next;
}

/** Activity rows on this form, numbered, for the expense Activity column. */
export function activityLinkChoices(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
  gridName: string,
): SelectChoice[] {
  const source = fields.find((field) => field.name === gridName && field.type === "grid");
  if (!source) {
    return [];
  }
  const columns = parseGridColumnDefs(source);
  if (columns.length === 0) {
    return [];
  }
  const grid = parseGridValue(values[gridName] ?? "", columns.length);
  const nature = columns.findIndex((column) => /nature of activity/i.test(column.label));
  const from = columns.findIndex((column) => /^from site$/i.test(column.label));
  const to = columns.findIndex((column) => /^to site$/i.test(column.label));
  const choices: SelectChoice[] = [];
  grid.rows.forEach((row, index) => {
    const read = (columnIndex: number) =>
      columnIndex >= 0 ? (row[columnKey(columnIndex, columns.length)] ?? "").trim() : "";
    const title = read(nature);
    const route = [read(from), read(to)].filter(Boolean).join(" → ");
    if (title === "" && route === "") {
      return;
    }
    choices.push({
      value: String(index + 1),
      label: [`${index + 1}`, title, route].filter(Boolean).join(" — "),
    });
  });
  return choices;
}

export type ExpenseColumnNeed = "always" | "per_diem" | "vatable" | "vat_amount";

/** VAT treatment and VAT are only for a VATable type. Days is not a column. */
export function expenseColumnNeed(label: string): ExpenseColumnNeed {
  const name = label.trim().toLowerCase();
  if (name === "days") {
    return "per_diem";
  }
  if (name === "vat treatment") {
    return "vatable";
  }
  if (name === "vat") {
    return "vat_amount";
  }

  return "always";
}

function expenseTypeChoice(columns: GridColumnDef[], raw: string) {
  const typeIndex = columns.findIndex((column) => /^expense type$/i.test(column.label.trim()));
  if (typeIndex < 0) {
    return undefined;
  }

  return columns[typeIndex]?.choices?.find(
    (choice) => choice.value === raw || choice.label.trim().toLowerCase() === raw.toLowerCase(),
  );
}

export function expenseRowNeeds(
  columns: GridColumnDef[],
  row: Record<string, string>,
): { perDiem: boolean; vatableType: boolean; vatableAmount: boolean } {
  const typeIndex = columns.findIndex((column) => /^expense type$/i.test(column.label.trim()));
  if (typeIndex < 0) {
    return { perDiem: true, vatableType: true, vatableAmount: true };
  }

  const raw = (row[columnKey(typeIndex, columns.length)] ?? "").trim();
  if (raw === "") {
    return { perDiem: false, vatableType: false, vatableAmount: false };
  }

  const choice = expenseTypeChoice(columns, raw);
  const perDiem =
    choice?.data?.per_diem === true
    || /^per[ _-]?diem$/i.test(raw)
    || /per diem/i.test(choice?.label ?? "");
  const treatmentIndex = columns.findIndex((column) => /^vat treatment$/i.test(column.label.trim()));
  const treatment =
    treatmentIndex < 0
      ? ""
      : (row[columnKey(treatmentIndex, columns.length)] ?? "").trim().toLowerCase();
  const typeVatable = choice?.data?.vatable === true && !perDiem;
  const vatableType = !perDiem && (typeVatable || treatment === "vatable");
  const vatableAmount = !perDiem && (treatment === "vatable" || (treatment === "" && typeVatable));

  return { perDiem, vatableType, vatableAmount };
}

/** Liquidation types the actual in Amount. Quantity and Rate are not part of that line. */
export function liquidationTypesActualAmount(columns: GridColumnDef[]): boolean {
  return columns.some((column) => /^forecast amount$/i.test(column.label.trim()));
}

/** Liquidation (forecast beside the actual) or reimbursement (actual only). Amount is typed. */
export function expenseGridKeepsTypedAmount(columns: GridColumnDef[]): boolean {
  if (liquidationTypesActualAmount(columns)) {
    return true;
  }
  const labels = columns.map((column) => column.label.trim().toLowerCase());
  const has = (name: string) => labels.includes(name);

  return has("or no") && has("expense type") && has("amount");
}

export function expenseColumnVisible(
  columns: GridColumnDef[],
  rows: Array<Record<string, string>>,
  column: GridColumnDef,
): boolean {
  if (
    expenseGridKeepsTypedAmount(columns) &&
    /^(quantity|rate)$/i.test(column.label.trim())
  ) {
    return false;
  }
  const need = expenseColumnNeed(column.label);
  if (need === "per_diem") {
    return false;
  }
  if (need === "always") {
    return true;
  }
  if (!columns.some((item) => /^expense type$/i.test(item.label.trim()))) {
    return true;
  }

  return rows.some((row) => {
    const flags = expenseRowNeeds(columns, row);
    if (need === "per_diem") {
      return false;
    }
    if (need === "vatable") {
      return flags.vatableType;
    }

    return flags.vatableAmount;
  });
}

export function expenseCellApplies(
  columns: GridColumnDef[],
  row: Record<string, string>,
  column: GridColumnDef,
): boolean {
  const need = expenseColumnNeed(column.label);
  if (need === "per_diem") {
    return false;
  }
  if (need === "always") {
    return true;
  }
  if (!columns.some((item) => /^expense type$/i.test(item.label.trim()))) {
    return true;
  }

  const flags = expenseRowNeeds(columns, row);
  if (need === "per_diem") {
    return false;
  }
  if (need === "vatable") {
    return flags.vatableType;
  }

  return flags.vatableAmount;
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

export function isMoneyGridColumn(column: GridColumnDef, columns: GridColumnDef[] = []): boolean {
  if (column.type !== "currency") {
    return false;
  }

  const label = column.label.trim();
  if (/^forecast amount$/i.test(label)) {
    return false;
  }
  if (/^rate$/i.test(label)) {
    return !columns.some((item) => /^amount$/i.test(item.label.trim()));
  }

  return true;
}

export function isHighlightedTotalColumn(column: GridColumnDef): boolean {
  return isRowTotalLabel(column.label) || column.type === "currency" && /^total$/i.test(column.label.trim());
}

/** Column indexes that should show footer sums (money columns). */
export function summableGridColumnIndexes(columns: GridColumnDef[]): boolean[] {
  return columns.map((column) => isMoneyGridColumn(column, columns));
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
