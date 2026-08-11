import { fieldOptionsToRecord, mergeFieldOptions } from "@/modules/e-approval/field-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type MatrixAxisOption = {
  value: string;
  label: string;
};

export type MatrixFieldOptions = {
  rows: MatrixAxisOption[];
  columns: MatrixAxisOption[];
  /** When true, each row shows an optional free-text note. */
  row_notes?: boolean;
  /** Placeholder / header for the note column. */
  row_notes_label?: string;
};

export type MatrixRowAnswer = {
  value: string;
  note?: string;
};

/** Normalized in-memory state. Stored as flat `{row:col}` or `{answers,notes}`. */
export type MatrixFieldState = Record<string, MatrixRowAnswer>;

/** @deprecated Prefer MatrixFieldState — kept for callers that only need column answers. */
export type MatrixFieldValue = Record<string, string>;

export const DEFAULT_MATRIX_COLUMNS: MatrixAxisOption[] = [
  { value: "yes", label: "Yes" },
  { value: "no", label: "No" },
];

function parseAxisOption(entry: unknown, index: number, prefix: string): MatrixAxisOption | null {
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

function parseAxisList(raw: unknown, prefix: string): MatrixAxisOption[] {
  if (!Array.isArray(raw)) {
    return [];
  }

  const seen = new Set<string>();
  const out: MatrixAxisOption[] = [];
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

export function parseMatrixFieldOptions(field: EApprovalFormFieldInput): MatrixFieldOptions {
  const record = fieldOptionsToRecord(field);
  const rows = parseAxisList(record.rows, "row");
  const columns = parseAxisList(record.columns, "col");
  const notesLabel =
    typeof record.row_notes_label === "string" ? record.row_notes_label.trim() : "";

  return {
    rows:
      rows.length > 0
        ? rows
        : [
            { value: "a", label: "A. Item A" },
            { value: "b", label: "B. Item B" },
          ],
    columns: columns.length > 0 ? columns : [...DEFAULT_MATRIX_COLUMNS],
    row_notes: record.row_notes === true,
    row_notes_label: notesLabel !== "" ? notesLabel : "Notes",
  };
}

export function setMatrixFieldOptions(
  field: EApprovalFormFieldInput,
  options: Partial<MatrixFieldOptions>,
): Record<string, unknown> {
  const current = parseMatrixFieldOptions(field);

  return mergeFieldOptions(field, {
    rows: options.rows ?? current.rows,
    columns: options.columns ?? current.columns,
    row_notes: options.row_notes ?? current.row_notes ?? false,
    row_notes_label: options.row_notes_label ?? current.row_notes_label ?? "Notes",
  });
}

export function parseMatrixState(raw: string): MatrixFieldState {
  const trimmed = raw.trim();
  if (trimmed === "" || !trimmed.startsWith("{")) {
    return {};
  }

  try {
    const decoded = JSON.parse(trimmed) as unknown;
    if (!decoded || typeof decoded !== "object" || Array.isArray(decoded)) {
      return {};
    }

    const record = decoded as Record<string, unknown>;

    if (record.answers && typeof record.answers === "object" && !Array.isArray(record.answers)) {
      const answers = record.answers as Record<string, unknown>;
      const notesRaw =
        record.notes && typeof record.notes === "object" && !Array.isArray(record.notes)
          ? (record.notes as Record<string, unknown>)
          : {};
      const out: MatrixFieldState = {};
      for (const [row, col] of Object.entries(answers)) {
        const rowKey = row.trim();
        const colValue = String(col ?? "").trim();
        if (rowKey === "" || colValue === "") {
          continue;
        }
        const note = String(notesRaw[rowKey] ?? "").trim();
        out[rowKey] = note !== "" ? { value: colValue, note } : { value: colValue };
      }

      return out;
    }

    const out: MatrixFieldState = {};
    for (const [row, col] of Object.entries(record)) {
      const rowKey = row.trim();
      if (rowKey === "") {
        continue;
      }
      if (typeof col === "string") {
        const colValue = col.trim();
        if (colValue !== "") {
          out[rowKey] = { value: colValue };
        }
        continue;
      }
      if (col && typeof col === "object" && !Array.isArray(col)) {
        const entry = col as Record<string, unknown>;
        const colValue = String(entry.value ?? entry.v ?? "").trim();
        if (colValue === "") {
          continue;
        }
        const note = String(entry.note ?? entry.n ?? "").trim();
        out[rowKey] = note !== "" ? { value: colValue, note } : { value: colValue };
      }
    }

    return out;
  } catch {
    return {};
  }
}

/** Flat row→column map (ignores notes). */
export function parseMatrixValue(raw: string): MatrixFieldValue {
  const state = parseMatrixState(raw);
  const out: MatrixFieldValue = {};
  for (const [row, answer] of Object.entries(state)) {
    out[row] = answer.value;
  }

  return out;
}

export function serializeMatrixState(state: MatrixFieldState): string {
  const answers: Record<string, string> = {};
  const notes: Record<string, string> = {};

  for (const [row, answer] of Object.entries(state)) {
    const rowKey = row.trim();
    const colValue = (answer.value ?? "").trim();
    if (rowKey === "" || colValue === "") {
      continue;
    }
    answers[rowKey] = colValue;
    const note = (answer.note ?? "").trim();
    if (note !== "") {
      notes[rowKey] = note;
    }
  }

  if (Object.keys(answers).length === 0) {
    return "";
  }

  if (Object.keys(notes).length === 0) {
    return JSON.stringify(answers);
  }

  return JSON.stringify({ answers, notes });
}

export function serializeMatrixValue(value: MatrixFieldValue): string {
  const state: MatrixFieldState = {};
  for (const [row, col] of Object.entries(value)) {
    const rowKey = row.trim();
    const colValue = col.trim();
    if (rowKey === "" || colValue === "") {
      continue;
    }
    state[rowKey] = { value: colValue };
  }

  return serializeMatrixState(state);
}

export function setMatrixCellValue(
  current: string,
  rowValue: string,
  columnValue: string,
): string {
  const state = parseMatrixState(current);
  const row = rowValue.trim();
  const col = columnValue.trim();
  if (row === "" || col === "") {
    return serializeMatrixState(state);
  }

  const existing = state[row];
  state[row] = {
    value: col,
    ...(existing?.note ? { note: existing.note } : {}),
  };

  return serializeMatrixState(state);
}

export function setMatrixNoteValue(current: string, rowValue: string, note: string): string {
  const state = parseMatrixState(current);
  const row = rowValue.trim();
  if (row === "") {
    return serializeMatrixState(state);
  }

  const existing = state[row];
  if (!existing?.value) {
    return serializeMatrixState(state);
  }

  const trimmed = note.trim();
  if (trimmed === "") {
    state[row] = { value: existing.value };
  } else {
    state[row] = { value: existing.value, note: trimmed };
  }

  return serializeMatrixState(state);
}

export function matrixHasCompleteAnswers(
  value: string,
  options: MatrixFieldOptions,
): boolean {
  if (options.rows.length === 0) {
    return false;
  }

  const state = parseMatrixState(value);
  const allowed = new Set(options.columns.map((c) => c.value));

  return options.rows.every((row) => {
    const selected = state[row.value]?.value?.trim() ?? "";
    return selected !== "" && allowed.has(selected);
  });
}

export function resolveMatrixDisplayLabels(
  value: string,
  options: MatrixFieldOptions,
): string {
  const state = parseMatrixState(value);
  if (Object.keys(state).length === 0) {
    return "";
  }

  const rowLabels = new Map(options.rows.map((r) => [r.value, r.label]));
  const colLabels = new Map(options.columns.map((c) => [c.value, c.label]));

  return options.rows
    .map((row) => {
      const answer = state[row.value];
      if (!answer?.value) {
        return null;
      }
      const rowLabel = rowLabels.get(row.value) ?? row.value;
      const colLabel = colLabels.get(answer.value) ?? answer.value;
      const note = (answer.note ?? "").trim();

      return note !== "" ? `${rowLabel}: ${colLabel} (${note})` : `${rowLabel}: ${colLabel}`;
    })
    .filter((line): line is string => line !== null)
    .join("; ");
}
