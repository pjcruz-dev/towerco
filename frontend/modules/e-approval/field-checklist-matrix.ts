import {
  fieldOptionsToRecord,
  mergeFieldOptions,
  type GridColumnDef,
  type GridColumnType,
  type SelectChoice,
} from "@/modules/e-approval/field-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type ChecklistMatrixAxisOption = {
  value: string;
  label: string;
};

export type ChecklistMatrixColumnType = GridColumnType;

export type ChecklistMatrixColumnDef = {
  value: string;
  label: string;
  /** Cell control type — defaults to short text. */
  type?: ChecklistMatrixColumnType;
  /** Static dropdown choices when type is select. */
  choices?: SelectChoice[];
  /** Master-data lookup key when type is select. */
  master_data_key?: string;
};

export type ChecklistMatrixFieldOptions = {
  rows: ChecklistMatrixAxisOption[];
  columns: ChecklistMatrixColumnDef[];
  /** Header label for the checkbox + row name column (e.g. Cost Application). */
  row_select_label?: string;
};

export type ChecklistMatrixRowAnswer = {
  selected: boolean;
  cells: Record<string, string>;
};

/** Stored as JSON string: `{ [rowValue]: { selected, cells: { [colValue]: string } } }`. */
export type ChecklistMatrixFieldState = Record<string, ChecklistMatrixRowAnswer>;

export const CHECKLIST_MATRIX_COLUMN_TYPES: ChecklistMatrixColumnType[] = [
  "text",
  "number",
  "currency",
  "date",
  "select",
];

export const DEFAULT_CHECKLIST_MATRIX_COLUMNS: ChecklistMatrixColumnDef[] = [
  { value: "project_site_no", label: "Project Site No", type: "text" },
  { value: "ref_no", label: "Ref No", type: "text" },
  { value: "or_no", label: "OR No.", type: "text" },
];

export const DEFAULT_CHECKLIST_MATRIX_ROWS: ChecklistMatrixAxisOption[] = [
  { value: "saq_site_survey", label: "SAQ-Site Survey" },
  { value: "saq_permitting", label: "SAQ-Permitting" },
  { value: "saq_soil_testing", label: "SAQ Soil Testing" },
  { value: "cme_materials", label: "CME-Materials" },
  { value: "cme_labor", label: "CME-Labor" },
  { value: "logistics", label: "Logistics" },
  { value: "various_department", label: "Various Department" },
  { value: "finance_and_accounting", label: "Finance and Accounting" },
  { value: "others", label: "Others" },
];

function parseAxisOption(entry: unknown, index: number, prefix: string): ChecklistMatrixAxisOption | null {
  if (typeof entry === "string") {
    const label = entry.trim();
    if (label === "") {
      return null;
    }

    return { value: `${prefix}_${index + 1}`, label };
  }

  if (!entry || typeof entry !== "object" || Array.isArray(entry)) {
    return null;
  }

  const record = entry as Record<string, unknown>;
  const value = String(record.value ?? "").trim();
  const label = String(record.label ?? value).trim();
  if (value === "" && label === "") {
    return null;
  }

  return {
    value: value !== "" ? value : `${prefix}_${index + 1}`,
    label: label !== "" ? label : value,
  };
}

function parseAxisList(raw: unknown, prefix: string): ChecklistMatrixAxisOption[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  const seen = new Set<string>();
  const out: ChecklistMatrixAxisOption[] = [];
  raw.forEach((entry, index) => {
    const parsed = parseAxisOption(entry, index, prefix);
    if (!parsed || seen.has(parsed.value)) {
      return;
    }
    seen.add(parsed.value);
    out.push(parsed);
  });

  return out;
}

function parseSelectChoicesList(raw: unknown): SelectChoice[] | undefined {
  if (!Array.isArray(raw) || raw.length === 0) {
    return undefined;
  }

  const out: SelectChoice[] = [];
  const seen = new Set<string>();
  raw.forEach((entry, index) => {
    if (typeof entry === "string") {
      const label = entry.trim();
      if (label === "" || seen.has(label)) {
        return;
      }
      seen.add(label);
      out.push({ value: `opt_${index + 1}`, label });
      return;
    }
    if (!entry || typeof entry !== "object" || Array.isArray(entry)) {
      return;
    }
    const record = entry as Record<string, unknown>;
    const value = String(record.value ?? "").trim();
    const label = String(record.label ?? value).trim();
    if (value === "" || seen.has(value)) {
      return;
    }
    seen.add(value);
    out.push({ value, label: label !== "" ? label : value });
  });

  return out.length > 0 ? out : undefined;
}

function parseColumnType(raw: unknown): ChecklistMatrixColumnType {
  const type = String(raw ?? "text").trim().toLowerCase();
  return (CHECKLIST_MATRIX_COLUMN_TYPES as string[]).includes(type)
    ? (type as ChecklistMatrixColumnType)
    : "text";
}

function parseColumnDef(entry: unknown, index: number): ChecklistMatrixColumnDef | null {
  const axis = parseAxisOption(entry, index, "col");
  if (!axis) {
    return null;
  }

  if (!entry || typeof entry !== "object" || Array.isArray(entry)) {
    return { ...axis, type: "text" };
  }

  const record = entry as Record<string, unknown>;
  const type = parseColumnType(record.type);
  const column: ChecklistMatrixColumnDef = { ...axis, type };

  if (type === "select") {
    const masterKey =
      typeof record.master_data_key === "string" ? record.master_data_key.trim() : "";
    if (masterKey !== "") {
      column.master_data_key = masterKey;
    } else {
      const choices = parseSelectChoicesList(record.choices);
      column.choices = choices ?? [{ value: "a", label: "Option A" }];
    }
  }

  return column;
}

function parseColumnList(raw: unknown): ChecklistMatrixColumnDef[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  const seen = new Set<string>();
  const out: ChecklistMatrixColumnDef[] = [];
  raw.forEach((entry, index) => {
    const parsed = parseColumnDef(entry, index);
    if (!parsed || seen.has(parsed.value)) {
      return;
    }
    seen.add(parsed.value);
    out.push(parsed);
  });

  return out;
}

export function checklistMatrixColumnType(column: ChecklistMatrixColumnDef): ChecklistMatrixColumnType {
  return column.type ?? "text";
}

/** Adapt a checklist column for the shared grid cell renderer. */
export function checklistMatrixColumnAsGridDef(column: ChecklistMatrixColumnDef): GridColumnDef {
  const type = checklistMatrixColumnType(column);
  const def: GridColumnDef = { label: column.label, type };
  if (type === "select") {
    if (column.master_data_key) {
      def.master_data_key = column.master_data_key;
    } else if (column.choices) {
      def.choices = column.choices;
    }
  }
  return def;
}

export function parseChecklistMatrixFieldOptions(field: EApprovalFormFieldInput): ChecklistMatrixFieldOptions {
  const record = fieldOptionsToRecord(field);
  const rows = parseAxisList(record.rows, "row");
  const columns = parseColumnList(record.columns);
  const rowSelectLabel =
    typeof record.row_select_label === "string" ? record.row_select_label.trim() : "";

  return {
    rows: rows.length > 0 ? rows : [...DEFAULT_CHECKLIST_MATRIX_ROWS],
    columns: columns.length > 0 ? columns : [...DEFAULT_CHECKLIST_MATRIX_COLUMNS],
    row_select_label: rowSelectLabel !== "" ? rowSelectLabel : "Cost Application",
  };
}

export function setChecklistMatrixFieldOptions(
  field: EApprovalFormFieldInput,
  options: Partial<ChecklistMatrixFieldOptions>,
): Record<string, unknown> {
  const current = parseChecklistMatrixFieldOptions(field);

  return mergeFieldOptions(field, {
    rows: options.rows ?? current.rows,
    columns: options.columns ?? current.columns,
    row_select_label: options.row_select_label ?? current.row_select_label ?? "Cost Application",
  });
}

function emptyCells(columns: ChecklistMatrixColumnDef[]): Record<string, string> {
  const cells: Record<string, string> = {};
  for (const column of columns) {
    cells[column.value] = "";
  }
  return cells;
}

export function parseChecklistMatrixState(
  raw: string,
  columns: ChecklistMatrixColumnDef[] = DEFAULT_CHECKLIST_MATRIX_COLUMNS,
): ChecklistMatrixFieldState {
  const trimmed = raw.trim();
  if (trimmed === "" || !trimmed.startsWith("{")) {
    return {};
  }

  try {
    const decoded = JSON.parse(trimmed) as unknown;
    if (!decoded || typeof decoded !== "object" || Array.isArray(decoded)) {
      return {};
    }

    const out: ChecklistMatrixFieldState = {};
    for (const [rowKey, entry] of Object.entries(decoded as Record<string, unknown>)) {
      const key = rowKey.trim();
      if (key === "") {
        continue;
      }

      if (typeof entry === "boolean") {
        out[key] = { selected: entry, cells: emptyCells(columns) };
        continue;
      }

      if (!entry || typeof entry !== "object" || Array.isArray(entry)) {
        continue;
      }

      const record = entry as Record<string, unknown>;
      const selected =
        record.selected === true ||
        record.checked === true ||
        record.selected === 1 ||
        record.checked === 1;

      const cells = emptyCells(columns);
      const cellsRaw =
        record.cells && typeof record.cells === "object" && !Array.isArray(record.cells)
          ? (record.cells as Record<string, unknown>)
          : record;

      for (const column of columns) {
        if (column.value === "selected" || column.value === "checked" || column.value === "cells") {
          continue;
        }
        cells[column.value] = String(cellsRaw[column.value] ?? "").trim();
      }

      out[key] = { selected, cells };
    }

    return out;
  } catch {
    return {};
  }
}

export function serializeChecklistMatrixState(state: ChecklistMatrixFieldState): string {
  const out: Record<string, { selected: boolean; cells: Record<string, string> }> = {};
  let hasAny = false;

  for (const [rowKey, answer] of Object.entries(state)) {
    const key = rowKey.trim();
    if (key === "") {
      continue;
    }

    const cells: Record<string, string> = {};
    let hasCell = false;
    for (const [col, value] of Object.entries(answer.cells ?? {})) {
      const trimmed = String(value ?? "").trim();
      cells[col] = trimmed;
      if (trimmed !== "") {
        hasCell = true;
      }
    }

    if (!answer.selected && !hasCell) {
      continue;
    }

    out[key] = { selected: answer.selected === true, cells };
    hasAny = true;
  }

  return hasAny ? JSON.stringify(out) : "";
}

export function setChecklistMatrixRowSelected(
  raw: string,
  rowValue: string,
  selected: boolean,
  columns: ChecklistMatrixColumnDef[],
): string {
  const state = parseChecklistMatrixState(raw, columns);
  const current = state[rowValue] ?? { selected: false, cells: emptyCells(columns) };
  state[rowValue] = {
    selected,
    cells: { ...emptyCells(columns), ...current.cells },
  };
  return serializeChecklistMatrixState(state);
}

export function setChecklistMatrixCellValue(
  raw: string,
  rowValue: string,
  columnValue: string,
  cellValue: string,
  columns: ChecklistMatrixColumnDef[],
): string {
  const state = parseChecklistMatrixState(raw, columns);
  const current = state[rowValue] ?? { selected: false, cells: emptyCells(columns) };
  const nextCells = { ...emptyCells(columns), ...current.cells, [columnValue]: cellValue };
  const hasCell = Object.values(nextCells).some((v) => v.trim() !== "");
  state[rowValue] = {
    selected: current.selected || hasCell,
    cells: nextCells,
  };
  return serializeChecklistMatrixState(state);
}

export function checklistMatrixHasSelection(
  raw: string,
  columns?: ChecklistMatrixColumnDef[],
): boolean {
  const state = parseChecklistMatrixState(raw, columns);
  return Object.values(state).some((answer) => answer.selected);
}

export function validateChecklistMatrixValue(
  raw: string,
  required: boolean,
  label: string,
  options: ChecklistMatrixFieldOptions,
): string | null {
  const state = parseChecklistMatrixState(raw, options.columns);
  const rowValues = new Set(options.rows.map((r) => r.value));
  const columnValues = new Set(options.columns.map((c) => c.value));

  for (const [rowKey, answer] of Object.entries(state)) {
    if (!rowValues.has(rowKey)) {
      return `${label} contains an invalid row.`;
    }
    for (const colKey of Object.keys(answer.cells)) {
      if (!columnValues.has(colKey)) {
        return `${label} contains an invalid column.`;
      }
    }
  }

  if (!required) {
    return null;
  }

  if (!checklistMatrixHasSelection(raw, options.columns)) {
    return `${label} requires at least one row to be selected.`;
  }

  return null;
}

function resolveCellDisplayLabel(column: ChecklistMatrixColumnDef, cell: string): string {
  if (cell === "") {
    return "";
  }
  if (checklistMatrixColumnType(column) !== "select") {
    return cell;
  }
  const choice = column.choices?.find((c) => c.value === cell);
  return choice?.label ?? cell;
}

export function formatChecklistMatrixDisplay(
  raw: string,
  options: ChecklistMatrixFieldOptions,
): string {
  const state = parseChecklistMatrixState(raw, options.columns);
  const lines: string[] = [];

  for (const row of options.rows) {
    const answer = state[row.value];
    if (!answer?.selected) {
      continue;
    }

    const parts = options.columns
      .map((column) => {
        const cell = (answer.cells[column.value] ?? "").trim();
        if (cell === "") {
          return null;
        }
        return `${column.label}: ${resolveCellDisplayLabel(column, cell)}`;
      })
      .filter((part): part is string => part !== null);

    lines.push(parts.length > 0 ? `${row.label} — ${parts.join("; ")}` : row.label);
  }

  return lines.join("\n");
}
