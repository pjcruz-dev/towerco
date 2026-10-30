import {
  parseChecklistMatrixFieldOptions,
  parseChecklistMatrixState,
} from "@/modules/e-approval/field-checklist-matrix";
import { parseGridColumnDefs, parseGridValue, type GridColumnDef } from "@/modules/e-approval/field-options";
import type { FormComposeStep } from "@/modules/e-approval/form-compose-steps";
import { formatCurrencyGrouping } from "@/lib/format-currency-input";
import { formatDisplayDate } from "@/lib/format-display-date";
import { formatDateRangeDisplay } from "@/modules/e-approval/field-type-options";
import { isComposeFillableFieldType } from "@/modules/e-approval/form-compose-structural";
import { expenseColumnVisible, expenseLineVatParts } from "@/modules/e-approval/grid-row-formulas";
import { parseSubmissionAmount } from "@/modules/e-approval/parent-submission-link";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type ComposeReviewTable = {
  headers: string[];
  rows: string[][];
};

export type ComposeReviewTotal = {
  label: string;
  value: string;
};

/** Place money totals under the matching table columns. Leftovers sit on a following row, right-aligned. */
export function placeReviewTotals(
  headers: string[],
  totals: ComposeReviewTotal[],
): Array<Array<ComposeReviewTotal | null>> {
  if (headers.length === 0 || totals.length === 0) {
    return [];
  }

  const row: Array<ComposeReviewTotal | null> = headers.map(() => null);
  const used = new Set<number>();

  const take = (headerPattern: RegExp, labelPattern: RegExp) => {
    const column = headers.findIndex((header) => headerPattern.test(header.trim()));
    const index = totals.findIndex(
      (total, totalIndex) => !used.has(totalIndex) && labelPattern.test(total.label.trim()),
    );
    if (column < 0 || index < 0 || row[column]) {
      return;
    }
    row[column] = totals[index] ?? null;
    used.add(index);
  };

  take(/^amount$/i, /requested amount|total expenses|total reimbursement|liquidation amount|^amount$/i);
  take(/^vat$/i, /^vat$/i);
  take(/^non-?vatable$/i, /^non-?vatable$/i);
  take(/net of vat/i, /net of vat/i);

  const rest = totals.filter((_, index) => !used.has(index));
  if (rest.length === 0) {
    return [row];
  }

  const extra: Array<ComposeReviewTotal | null> = headers.map(() => null);
  const start = Math.max(headers.length - rest.length, 0);
  rest.forEach((total, offset) => {
    extra[start + offset] = total;
  });

  return [row, extra];
}

export type ComposeReviewSummaryRow = {
  fieldName: string;
  label: string;
  value: string;
  highlight: boolean;
  fieldType: string;
  /** When set, render as a table instead of a single-line value. */
  table?: ComposeReviewTable | null;
  /** Money totals that belong under the grid, not in the answer list. */
  totals?: ComposeReviewTotal[];
};

const GRID_MONEY_TOTAL_NAMES = new Set([
  "requested_amount",
  "total_reimbursement",
  "vat_total",
  "non_vatable_total",
  "vatable_net",
]);

function fieldOptionsRecord(field: EApprovalFormFieldInput): Record<string, unknown> {
  if (!field.options || typeof field.options !== "object" || Array.isArray(field.options)) {
    return {};
  }

  return field.options;
}

/** Currency figures summed from a line grid (requested amount, VAT, and the VAT split). */
export function isGridMoneyTotalField(field: EApprovalFormFieldInput): boolean {
  if (field.type === "grid") {
    return false;
  }

  const key = field.name.trim().toLowerCase();
  if (GRID_MONEY_TOTAL_NAMES.has(key)) {
    return true;
  }

  const options = fieldOptionsRecord(field);
  const layout = options.layout;
  if (layout && typeof layout === "object" && !Array.isArray(layout)) {
    if (String((layout as { row_id?: unknown }).row_id ?? "") === "money_footer") {
      return true;
    }
  }

  const computed = options.computed_from;
  if (computed && typeof computed === "object" && !Array.isArray(computed)) {
    if (String((computed as { operation?: unknown }).operation ?? "") === "sum_grid_column") {
      return true;
    }
  }

  return false;
}

export function moneyTotalSourceField(field: EApprovalFormFieldInput): string | null {
  const computed = fieldOptionsRecord(field).computed_from;
  if (!computed || typeof computed !== "object" || Array.isArray(computed)) {
    return null;
  }

  const source = String((computed as { source_field?: unknown }).source_field ?? "").trim();
  return source !== "" ? source : null;
}

/** Prefer these fields at the top of the review summary when present. */
const HIGHLIGHT_FIELD_NAMES = [
  "payee",
  "payment_amount",
  "amount",
  "currency",
  "bank_name",
  "bank_account_name",
  "bank_account_no",
  "cost_application",
  "cost_category",
] as const;

export const COMPOSE_REVIEW_STEP_ID = "ea-compose-review";

function tryParseJson(raw: string): unknown {
  try {
    return JSON.parse(raw) as unknown;
  } catch {
    return null;
  }
}

function formatSiteStopDisplay(raw: string): string | null {
  if (!raw.startsWith("{")) {
    return null;
  }

  let parsed: unknown;
  try {
    parsed = JSON.parse(raw) as unknown;
  } catch {
    return null;
  }

  if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
    return null;
  }

  const record = parsed as { site?: unknown; location?: unknown; fields?: unknown; sites?: unknown };
  if (Array.isArray(record.sites)) {
    const lines = record.sites
      .map((entry) => formatSiteStopDisplay(typeof entry === "string" ? entry : JSON.stringify(entry)))
      .filter((line): line is string => line !== null && line !== "—");
    return lines.length > 0 ? lines.join("\n") : "—";
  }
  if (!("site" in record) && !("location" in record)) {
    return null;
  }

  const site = String(record.site ?? "").trim();
  const location = String(record.location ?? "").trim();
  const extras: string[] = [];
  if (record.fields && typeof record.fields === "object" && !Array.isArray(record.fields)) {
    for (const [key, value] of Object.entries(record.fields as Record<string, unknown>)) {
      if (key === "subsidiary") {
        continue;
      }
      const text = String(value ?? "").trim();
      if (text !== "" && text !== location && text !== site) {
        extras.push(text);
      }
    }
  }
  if (site === "" && location === "" && extras.length === 0) {
    return "—";
  }
  if (extras.length === 0) {
    if (location === "") {
      return site;
    }
    if (site === "") {
      return location;
    }
    return `${site} — ${location}`;
  }

  const lines = [site, location, ...extras].filter((line) => line !== "");
  return lines.join("\n");
}

function selectChoiceLabel(column: GridColumnDef, value: string): string | null {
  const match = (column.choices ?? []).find((choice) => choice.value === value);
  const label = match?.label?.trim() ?? "";
  return label !== "" ? label : null;
}

function cellDisplay(value: string | undefined, column?: GridColumnDef): string {
  const trimmed = (value ?? "").trim();
  if (trimmed === "") {
    return "—";
  }

  if (trimmed.startsWith("{") && trimmed.includes('"from"')) {
    const withTime = column?.type === "date_range" ? column.include_time !== false : true;
    const formatted = formatDateRangeDisplay(trimmed, "", withTime);
    if (formatted && !formatted.startsWith("{")) {
      return formatted;
    }
  }

  const siteStop = formatSiteStopDisplay(trimmed);
  if (siteStop) {
    return siteStop;
  }

  if (column?.type === "select") {
    const label = selectChoiceLabel(column, trimmed);
    if (label) {
      return label;
    }
  }

  if (column?.type === "currency") {
    return formatCurrencyGrouping(trimmed) || trimmed;
  }

  return trimmed;
}

export function buildChecklistMatrixReviewTable(
  field: EApprovalFormFieldInput,
  rawValue: string | undefined,
): ComposeReviewTable | null {
  const options = parseChecklistMatrixFieldOptions(field);
  const state = parseChecklistMatrixState(rawValue ?? "", options.columns);
  const selectedRows = options.rows.filter((row) => state[row.value]?.selected === true);

  if (selectedRows.length === 0) {
    return null;
  }

  const headers = [
    options.row_select_label || "Cost Application",
    ...options.columns.map((column) => column.label),
  ];

  const rows = selectedRows.map((row) => {
    const answer = state[row.value] ?? { selected: true, cells: {} };
    return [
      row.label,
      ...options.columns.map((column) => cellDisplay(answer.cells[column.value])),
    ];
  });

  return { headers, rows };
}

export function buildGridReviewTable(
  field: EApprovalFormFieldInput,
  rawValue: string | undefined,
): ComposeReviewTable | null {
  const columns = parseGridColumnDefs(field);
  if (columns.length === 0) {
    return null;
  }

  const parsed = parseGridValue(
    rawValue ?? "",
    columns.length,
    columns.map((column) => column.label),
  );
  const dataRows = parsed.rows.filter((row) =>
    Object.values(row).some((cell) => String(cell ?? "").trim() !== ""),
  );

  if (dataRows.length === 0) {
    return null;
  }

  const visible = columns
    .map((column, index) => ({ column, index }))
    .filter(({ column }) => expenseColumnVisible(columns, dataRows, column));
  const headers = visible.map(({ column }) => column.label);
  const rows = dataRows.map((row) =>
    visible.map(({ column, index }) => cellDisplay(row[String(index)] ?? row[column.label], column)),
  );
  const withSplit = appendVatSplitReviewColumns(columns, dataRows, headers, rows);

  return withSplit;
}

function moneyReviewCell(value: number): string {
  return formatCurrencyGrouping(value.toFixed(2)) || value.toFixed(2);
}

/** Line columns for the two VAT totals, so each total sits under its own figures. */
function splitColumnRole(label: string): "non" | "net" | null {
  if (/^non-?vatable$/i.test(label.trim())) {
    return "non";
  }
  if (/net of vat/i.test(label.trim())) {
    return "net";
  }
  return null;
}

function appendVatSplitReviewColumns(
  columns: GridColumnDef[],
  dataRows: Array<Record<string, string>>,
  headers: string[],
  rows: string[][],
): ComposeReviewTable {
  const parts = dataRows.map((row) => expenseLineVatParts(columns, row));
  if (parts.every((part) => part === null)) {
    return { headers, rows };
  }

  const ownsNon = columns.some((column) => splitColumnRole(column.label) === "non");
  const ownsNet = columns.some((column) => splitColumnRole(column.label) === "net");
  if (ownsNon || ownsNet) {
    return {
      headers,
      rows: rows.map((row, index) => {
        const part = parts[index];
        return row.map((cell, cellIndex) => {
          const role = splitColumnRole(headers[cellIndex] ?? "");
          if (role === "non") {
            return moneyReviewCell(part?.nonVatable ?? 0);
          }
          if (role === "net") {
            return moneyReviewCell(part?.vatableNet ?? 0);
          }
          return cell;
        });
      }),
    };
  }

  return {
    headers: [...headers, "Non-VATable", "VATable, net of VAT"],
    rows: rows.map((row, index) => {
      const part = parts[index];
      return [
        ...row,
        moneyReviewCell(part?.nonVatable ?? 0),
        moneyReviewCell(part?.vatableNet ?? 0),
      ];
    }),
  };
}

function formatChecklistMatrixSummary(
  field: EApprovalFormFieldInput,
  raw: string,
): { text: string; table: ComposeReviewTable | null } {
  const table = buildChecklistMatrixReviewTable(field, raw);
  if (!table) {
    return { text: "—", table: null };
  }

  return {
    text: `${table.rows.length} selected row${table.rows.length === 1 ? "" : "s"}`,
    table,
  };
}

function formatGridSummary(
  field: EApprovalFormFieldInput,
  raw: string,
): { text: string; table: ComposeReviewTable | null } {
  const table = buildGridReviewTable(field, raw);
  if (!table) {
    return { text: "—", table: null };
  }

  return {
    text: `${table.rows.length} line${table.rows.length === 1 ? "" : "s"}`,
    table,
  };
}

export function formatComposeReviewValue(
  field: EApprovalFormFieldInput,
  rawValue: string | undefined,
  fileSelections?: Record<string, File[]>,
): string {
  return buildComposeReviewSummaryRow(field, rawValue, fileSelections).value;
}

export function buildComposeReviewSummaryRow(
  field: EApprovalFormFieldInput,
  rawValue: string | undefined,
  fileSelections?: Record<string, File[]>,
): ComposeReviewSummaryRow {
  const base = {
    fieldName: field.name,
    label: field.label?.trim() || field.name,
    highlight: (HIGHLIGHT_FIELD_NAMES as readonly string[]).includes(field.name),
    fieldType: field.type,
  };

  if (field.type === "file" || field.type === "camera") {
    const files = fileSelections?.[field.name] ?? [];
    if (files.length > 0) {
      return {
        ...base,
        value: `${files.length} file${files.length === 1 ? "" : "s"} selected`,
        table: null,
      };
    }
    const trimmed = (rawValue ?? "").trim();
    return {
      ...base,
      value: trimmed !== "" ? trimmed : "—",
      table: null,
    };
  }

  const value = (rawValue ?? "").trim();
  if (value === "") {
    return { ...base, value: "—", table: null };
  }

  if (field.type === "currency") {
    return { ...base, value: formatCurrencyGrouping(value) || "—", table: null };
  }

  if (field.type === "date_range") {
    return { ...base, value: formatDateRangeDisplay(value), table: null };
  }

  if (field.type === "date" || field.type === "datetime") {
    return { ...base, value: formatDisplayDate(value, field.type) ?? value, table: null };
  }

  if (field.type === "checklist_matrix") {
    const summary = formatChecklistMatrixSummary(field, value);
    return { ...base, value: summary.text, table: summary.table };
  }

  if (field.type === "grid") {
    const summary = formatGridSummary(field, value);
    return { ...base, value: summary.text, table: summary.table };
  }

  if (field.type === "matrix" || field.type === "size_matrix") {
    const parsed = tryParseJson(value);
    if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
      const entries = Object.entries(parsed as Record<string, unknown>).filter(
        ([, cell]) => String(cell ?? "").trim() !== "",
      );
      if (entries.length === 0) {
        return { ...base, value: "—", table: null };
      }
      return {
        ...base,
        value: `${entries.length} answer${entries.length === 1 ? "" : "s"}`,
        table: {
          headers: ["Item", "Value"],
          rows: entries.map(([key, cell]) => [key, cellDisplay(String(cell ?? ""))]),
        },
      };
    }
  }

  if (field.type === "checkbox" || field.type === "tags") {
    const parsed = tryParseJson(value);
    if (Array.isArray(parsed)) {
      return {
        ...base,
        value: parsed.length > 0 ? parsed.map(String).join(", ") : "—",
        table: null,
      };
    }
  }

  if (value.length > 160) {
    return { ...base, value: `${value.slice(0, 157)}…`, table: null };
  }

  return { ...base, value, table: null };
}

export function buildComposeReviewSummaryRows(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
  fileSelections?: Record<string, File[]>,
): ComposeReviewSummaryRow[] {
  const fillable = fields.filter(
    (field) => isComposeFillableFieldType(field.type) && field.type !== "approver" && field.type !== "signature",
  );

  const highlightRank = new Map<string, number>(
    HIGHLIGHT_FIELD_NAMES.map((name, index) => [name, index]),
  );

  const highlighted: ComposeReviewSummaryRow[] = [];
  const rest: ComposeReviewSummaryRow[] = [];
  const moneyTotals: Array<{ source: string | null; row: ComposeReviewSummaryRow }> = [];

  for (const field of fillable) {
    const row = buildComposeReviewSummaryRow(field, values[field.name], fileSelections);

    if (isGridMoneyTotalField(field)) {
      moneyTotals.push({ source: moneyTotalSourceField(field), row });
      continue;
    }

    if (row.highlight) {
      highlighted.push(row);
    } else {
      rest.push(row);
    }
  }

  highlighted.sort(
    (a, b) => (highlightRank.get(a.fieldName) ?? 0) - (highlightRank.get(b.fieldName) ?? 0),
  );

  const rows = [...highlighted, ...rest];
  const unmatchedNames = new Set(
    bindMoneyTotalsToGrids(
      rows,
      moneyTotals.map((total) => ({
        fieldName: total.row.fieldName,
        source: total.source,
        label: total.row.label,
        value: total.row.value,
      })),
    ),
  );
  const unmatched = moneyTotals
    .map((total) => total.row)
    .filter((row) => unmatchedNames.has(row.fieldName));

  return unmatched.length > 0 ? [...rows, ...unmatched] : rows;
}

/** Put money totals on the grid they were summed from. Returns field names that had no grid. */
export function bindMoneyTotalsToGrids<
  T extends { fieldName: string; table?: ComposeReviewTable | null; totals?: ComposeReviewTotal[] },
>(
  rows: T[],
  moneyTotals: Array<{ fieldName: string; source: string | null; label: string; value: string }>,
): string[] {
  const gridRows = rows.filter((row) => row.table);
  const unmatched: string[] = [];

  for (const total of moneyTotals) {
    const target =
      (total.source ? gridRows.find((row) => row.fieldName === total.source) : undefined) ??
      gridRows[gridRows.length - 1];
    if (!target?.table) {
      unmatched.push(total.fieldName);
      continue;
    }
    target.totals = [...(target.totals ?? []), { label: total.label, value: total.value }];
  }

  for (const row of gridRows) {
    appendMissingSplitTotals(row);
  }

  return unmatched;
}

/**
 * A grid that opts into column totals only shows those columns.
 * Stored VAT figures with no visible column stay off the footer.
 */
export function totalsForMarkedColumns(
  field: EApprovalFormFieldInput,
  headers: string[],
  totals: ComposeReviewTotal[] | undefined,
): ComposeReviewTotal[] | undefined {
  const columns = parseGridColumnDefs(field);
  if (!columns.some((column) => column.show_total === true)) {
    return totals;
  }

  const visible = new Set(headers.map((header) => header.trim().toLowerCase()));
  const marked = new Set(
    columns
      .filter((column) => column.show_total === true && visible.has(column.label.trim().toLowerCase()))
      .map((column) => column.label.trim().toLowerCase()),
  );
  if (marked.size === 0) {
    return [];
  }

  return (totals ?? []).filter((total) => markedColumnOwnsTotal(marked, total.label));
}

function markedColumnOwnsTotal(marked: Set<string>, label: string): boolean {
  const name = label.trim().toLowerCase();
  if (marked.has("amount") && /requested amount|total expenses|total reimbursement|liquidation amount|^amount$/.test(name)) {
    return true;
  }
  if (marked.has("vat") && name === "vat") {
    return true;
  }
  for (const header of marked) {
    if (header === name) {
      return true;
    }
    if (/^non-?vatable$/.test(header) && /^non-?vatable$/.test(name)) {
      return true;
    }
    if (/net of vat/.test(header) && /net of vat/.test(name)) {
      return true;
    }
  }
  return false;
}

const SPLIT_REVIEW_TOTALS: Array<{ header: RegExp; label: string }> = [
  { header: /^vat$/i, label: "VAT" },
  { header: /^non-?vatable$/i, label: "Non-VATable" },
  { header: /net of vat/i, label: "VATable, net of VAT" },
];

function columnMoneySum(table: ComposeReviewTable, index: number): string {
  let sum = 0;
  for (const row of table.rows) {
    sum += parseSubmissionAmount(row[index]) ?? 0;
  }
  const canonical = (Math.round(sum * 100) / 100).toFixed(2);
  return formatCurrencyGrouping(canonical) || canonical;
}

/** Liquidation stores one amount total. VAT, Non-VATable, and net still come from the lines. */
function appendMissingSplitTotals(row: {
  table?: ComposeReviewTable | null;
  totals?: ComposeReviewTotal[];
}): void {
  if (!row.table) {
    return;
  }

  const totals = row.totals ?? [];
  const extras: ComposeReviewTotal[] = [];
  for (const spec of SPLIT_REVIEW_TOTALS) {
    const already = totals.some((total) => spec.header.test(total.label.trim()));
    if (already) {
      continue;
    }
    const index = row.table.headers.findIndex((header) => spec.header.test(header.trim()));
    if (index < 0) {
      continue;
    }
    extras.push({ label: spec.label, value: columnMoneySum(row.table, index) });
  }

  if (extras.length > 0) {
    row.totals = [...totals, ...extras];
  }
}

export function isComposeReviewStepId(stepId: string): boolean {
  return stepId === COMPOSE_REVIEW_STEP_ID;
}

export function appendComposeReviewStep(steps: FormComposeStep[]): FormComposeStep[] {
  if (steps.length === 0) {
    return steps;
  }

  return [
    ...steps,
    {
      id: COMPOSE_REVIEW_STEP_ID,
      stepIndex: steps.length,
      label: "Review & submit",
      sectionFieldIndex: null,
      fieldIndices: [],
      fields: [],
    },
  ];
}
