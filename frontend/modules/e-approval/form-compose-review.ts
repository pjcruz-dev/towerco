import {
  parseChecklistMatrixFieldOptions,
  parseChecklistMatrixState,
} from "@/modules/e-approval/field-checklist-matrix";
import { parseGridColumnDefs, parseGridValue } from "@/modules/e-approval/field-options";
import type { FormComposeStep } from "@/modules/e-approval/form-compose-steps";
import { formatCurrencyGrouping } from "@/lib/format-currency-input";
import { isComposeFillableFieldType } from "@/modules/e-approval/form-compose-structural";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type ComposeReviewTable = {
  headers: string[];
  rows: string[][];
};

export type ComposeReviewSummaryRow = {
  fieldName: string;
  label: string;
  value: string;
  highlight: boolean;
  fieldType: string;
  /** When set, render as a table instead of a single-line value. */
  table?: ComposeReviewTable | null;
};

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

function cellDisplay(value: string | undefined): string {
  const trimmed = (value ?? "").trim();
  return trimmed !== "" ? trimmed : "—";
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

  return {
    headers: columns.map((column) => column.label),
    rows: dataRows.map((row) =>
      columns.map((_, index) => cellDisplay(row[String(index)] ?? row[columns[index]!.label])),
    ),
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

  for (const field of fillable) {
    const row = buildComposeReviewSummaryRow(field, values[field.name], fileSelections);

    if (row.highlight) {
      highlighted.push(row);
    } else {
      rest.push(row);
    }
  }

  highlighted.sort(
    (a, b) => (highlightRank.get(a.fieldName) ?? 0) - (highlightRank.get(b.fieldName) ?? 0),
  );

  return [...highlighted, ...rest];
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
