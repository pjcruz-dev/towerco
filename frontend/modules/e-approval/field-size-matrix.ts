import { fieldOptionsToRecord, mergeFieldOptions } from "@/modules/e-approval/field-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

/** `size` = W × H + NA; `text` = free-text line (Other / Existing Utilities). */
export type SizeMatrixRowInput = "size" | "text";

export type SizeMatrixRow = {
  value: string;
  label: string;
  input?: SizeMatrixRowInput;
};

export type SizeMatrixRowValue = {
  w?: string;
  h?: string;
  na?: boolean;
  text?: string;
};

/** Stored as JSON: `{ [rowValue]: { w?, h?, na? } | { text? } }`. */
export type SizeMatrixValue = Record<string, SizeMatrixRowValue>;

export const DEFAULT_SIZE_MATRIX_ROWS: SizeMatrixRow[] = [
  { value: "roofdeck", label: "Roofdeck", input: "size" },
  { value: "elevator_shaft", label: "Elevator Shaft", input: "size" },
  { value: "water_tank", label: "Water Tank", input: "size" },
  { value: "wall", label: "Wall", input: "size" },
  { value: "other", label: "Other (specify)", input: "text" },
  { value: "existing_utilities", label: "Existing Utilities", input: "text" },
];

export function sizeMatrixRowInput(row: SizeMatrixRow): SizeMatrixRowInput {
  return row.input === "text" ? "text" : "size";
}

function parseRowInput(raw: unknown): SizeMatrixRowInput {
  return String(raw ?? "").trim().toLowerCase() === "text" ? "text" : "size";
}

function parseRow(entry: unknown, index: number): SizeMatrixRow | null {
  if (typeof entry === "string") {
    const label = entry.trim();
    if (label === "") {
      return null;
    }

    return { value: `row_${index + 1}`, label, input: "size" };
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
    value: value !== "" ? value : `row_${index + 1}`,
    label: label !== "" ? label : value,
    input: parseRowInput(record.input),
  };
}

export function parseSizeMatrixRows(field: EApprovalFormFieldInput): SizeMatrixRow[] {
  const record = fieldOptionsToRecord(field);
  const raw = record.rows;
  if (!Array.isArray(raw)) {
    return [...DEFAULT_SIZE_MATRIX_ROWS];
  }

  const seen = new Set<string>();
  const out: SizeMatrixRow[] = [];
  raw.forEach((entry, index) => {
    const parsed = parseRow(entry, index);
    if (!parsed || seen.has(parsed.value)) {
      return;
    }
    seen.add(parsed.value);
    out.push(parsed);
  });

  return out.length > 0 ? out : [...DEFAULT_SIZE_MATRIX_ROWS];
}

export function setSizeMatrixRows(
  field: EApprovalFormFieldInput,
  rows: SizeMatrixRow[],
): Record<string, unknown> {
  return mergeFieldOptions(field, {
    rows: rows.map((row) => ({
      value: row.value,
      label: row.label,
      input: sizeMatrixRowInput(row),
    })),
  });
}

export function parseSizeMatrixValue(raw: string): SizeMatrixValue {
  const trimmed = raw.trim();
  if (trimmed === "" || !trimmed.startsWith("{")) {
    return {};
  }

  try {
    const decoded = JSON.parse(trimmed) as unknown;
    if (!decoded || typeof decoded !== "object" || Array.isArray(decoded)) {
      return {};
    }

    const out: SizeMatrixValue = {};
    for (const [rowKey, rowRaw] of Object.entries(decoded as Record<string, unknown>)) {
      const key = rowKey.trim();
      if (key === "" || !rowRaw || typeof rowRaw !== "object" || Array.isArray(rowRaw)) {
        continue;
      }
      const record = rowRaw as Record<string, unknown>;
      const row: SizeMatrixRowValue = {};
      if (record.na === true) {
        row.na = true;
      }
      const w = record.w == null ? "" : String(record.w).trim();
      const h = record.h == null ? "" : String(record.h).trim();
      const text = record.text == null ? "" : String(record.text).trim();
      if (w !== "") {
        row.w = w;
      }
      if (h !== "") {
        row.h = h;
      }
      if (text !== "") {
        row.text = text;
      }
      if (row.na || row.w || row.h || row.text) {
        out[key] = row;
      }
    }

    return out;
  } catch {
    return {};
  }
}

export function serializeSizeMatrixValue(value: SizeMatrixValue): string {
  const cleaned: SizeMatrixValue = {};
  for (const [rowKey, row] of Object.entries(value)) {
    const key = rowKey.trim();
    if (key === "") {
      continue;
    }
    if (row.na) {
      cleaned[key] = { na: true };
      continue;
    }
    const text = (row.text ?? "").trim();
    if (text !== "") {
      cleaned[key] = { text };
      continue;
    }
    const next: SizeMatrixRowValue = {};
    const w = (row.w ?? "").trim();
    const h = (row.h ?? "").trim();
    if (w !== "") {
      next.w = w;
    }
    if (h !== "") {
      next.h = h;
    }
    if (next.w || next.h) {
      cleaned[key] = next;
    }
  }

  return Object.keys(cleaned).length > 0 ? JSON.stringify(cleaned) : "";
}

export function setSizeMatrixRowValue(
  current: string,
  rowValue: string,
  patch: Partial<SizeMatrixRowValue>,
): string {
  const state = parseSizeMatrixValue(current);
  const key = rowValue.trim();
  if (key === "") {
    return serializeSizeMatrixValue(state);
  }

  const existing = state[key] ?? {};

  if (patch.text !== undefined) {
    const text = patch.text.trim();
    if (text === "") {
      delete state[key];
    } else {
      state[key] = { text };
    }
    return serializeSizeMatrixValue(state);
  }

  if (patch.na === true) {
    state[key] = { na: true };
    return serializeSizeMatrixValue(state);
  }

  const next: SizeMatrixRowValue = {
    w: patch.w !== undefined ? patch.w : existing.w,
    h: patch.h !== undefined ? patch.h : existing.h,
  };
  if (patch.na === false) {
    // keep w/h only
  } else if (existing.na && patch.w === undefined && patch.h === undefined) {
    next.na = true;
  }

  if (next.na) {
    state[key] = { na: true };
  } else {
    const cleaned: SizeMatrixRowValue = {};
    if ((next.w ?? "").trim() !== "") {
      cleaned.w = (next.w ?? "").trim();
    }
    if ((next.h ?? "").trim() !== "") {
      cleaned.h = (next.h ?? "").trim();
    }
    if (cleaned.w || cleaned.h) {
      state[key] = cleaned;
    } else {
      delete state[key];
    }
  }

  return serializeSizeMatrixValue(state);
}

export function sizeMatrixRowIsComplete(row: SizeMatrixRowValue | undefined): boolean {
  if (!row) {
    return false;
  }
  if (row.na) {
    return true;
  }
  if ((row.text ?? "").trim() !== "") {
    return true;
  }
  return (row.w ?? "").trim() !== "" && (row.h ?? "").trim() !== "";
}

/** Required completeness: every size row needs W×H or NA. Text rows are optional. */
export function sizeMatrixHasCompleteAnswers(value: string, rows: SizeMatrixRow[]): boolean {
  const sizeRows = rows.filter((row) => sizeMatrixRowInput(row) === "size");
  if (sizeRows.length === 0) {
    return rows.length > 0;
  }
  const state = parseSizeMatrixValue(value);

  return sizeRows.every((row) => {
    const entry = state[row.value];
    if (!entry || entry.na) {
      return entry?.na === true;
    }
    return (entry.w ?? "").trim() !== "" && (entry.h ?? "").trim() !== "";
  });
}

export function resolveSizeMatrixDisplayLabels(value: string, rows: SizeMatrixRow[]): string {
  const state = parseSizeMatrixValue(value);
  if (Object.keys(state).length === 0) {
    return "";
  }

  return rows
    .map((row) => {
      const entry = state[row.value];
      if (!entry) {
        return null;
      }
      if (sizeMatrixRowInput(row) === "text") {
        const text = (entry.text ?? "").trim();
        return text !== "" ? `${row.label}: ${text}` : null;
      }
      if (entry.na) {
        return `${row.label}: NA`;
      }
      const w = (entry.w ?? "").trim();
      const h = (entry.h ?? "").trim();
      if (w === "" && h === "") {
        return null;
      }

      return `${row.label}: ${w || "—"} × ${h || "—"}`;
    })
    .filter((line): line is string => line !== null)
    .join("; ");
}
