import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

/** Inline input shown next to a checkbox option (e.g. height + "m.(AGL)" or W × H). */
export type SelectChoiceCompanionInput = {
  key: string;
  type: "text" | "number" | "size";
  suffix?: string;
  placeholder?: string;
  required?: boolean;
};

export type SelectChoice = {
  value: string;
  label: string;
  subtitle?: string | null;
  /** Optional guidance under the option (always visible). */
  help?: string | null;
  /** Checkbox-only: companion fields on the same row as the option. */
  inputs?: SelectChoiceCompanionInput[];
};

export type GridColumnType = "text" | "number" | "currency" | "date" | "select";

export type GridColumnDef = {
  label: string;
  type: GridColumnType;
  master_data_key?: string;
  choices?: SelectChoice[];
};

export const GRID_COLUMN_TYPE_LABELS: Record<GridColumnType, string> = {
  text: "Short text",
  number: "Number",
  currency: "Currency",
  date: "Date",
  select: "Dropdown list",
};

/** Parse legacy pipe choices (`Label|CODE`) and modern `{ choices: [...] }` shapes. */
export function parseSelectChoices(field: EApprovalFormFieldInput): SelectChoice[] {
  const raw = field.options;
  if (raw === null || raw === undefined) {
    return [];
  }

  const entries: unknown[] = Array.isArray(raw)
    ? raw
    : typeof raw === "object" && Array.isArray((raw as { choices?: unknown }).choices)
      ? ((raw as { choices: unknown[] }).choices ?? [])
      : [];

  return entries
    .map(parseChoiceEntry)
    .filter((c): c is SelectChoice => c !== null);
}

function parseCompanionInputs(raw: unknown): SelectChoiceCompanionInput[] | undefined {
  if (!Array.isArray(raw) || raw.length === 0) {
    return undefined;
  }

  const inputs: SelectChoiceCompanionInput[] = [];
  const seen = new Set<string>();

  for (const item of raw) {
    if (!item || typeof item !== "object" || Array.isArray(item)) {
      continue;
    }
    const row = item as Record<string, unknown>;
    const key = String(row.key ?? "").trim();
    if (key === "" || seen.has(key)) {
      continue;
    }
    seen.add(key);
    const typeRaw = String(row.type ?? "text").trim().toLowerCase();
    const type: "text" | "number" | "size" =
      typeRaw === "number" ? "number" : typeRaw === "size" ? "size" : "text";
    const suffix = typeof row.suffix === "string" ? row.suffix.trim() : "";
    const placeholder = typeof row.placeholder === "string" ? row.placeholder.trim() : "";
    const input: SelectChoiceCompanionInput = { key, type };
    if (suffix) {
      input.suffix = suffix;
    }
    if (placeholder) {
      input.placeholder = placeholder;
    }
    if (row.required === true) {
      input.required = true;
    }
    inputs.push(input);
  }

  return inputs.length > 0 ? inputs : undefined;
}

function parseChoiceEntry(entry: unknown): SelectChoice | null {
  if (typeof entry === "object" && entry !== null && "value" in entry) {
    const row = entry as {
      value: unknown;
      label?: unknown;
      subtitle?: unknown;
      help?: unknown;
      inputs?: unknown;
    };
    const value = String(row.value ?? "").trim();
    if (value === "") {
      return null;
    }
    const label = String(row.label ?? value).trim();
    const choice: SelectChoice = { value, label: label !== "" ? label : value };
    if (typeof row.subtitle === "string" && row.subtitle.trim() !== "") {
      choice.subtitle = row.subtitle.trim();
    }
    if (typeof row.help === "string" && row.help.trim() !== "") {
      choice.help = row.help.trim();
    }
    const inputs = parseCompanionInputs(row.inputs);
    if (inputs) {
      choice.inputs = inputs;
    }

    return choice;
  }

  if (typeof entry !== "string") {
    return null;
  }

  const text = entry.trim();
  if (text === "") {
    return null;
  }

  const pipe = text.indexOf("|");
  if (pipe >= 0) {
    const label = text.slice(0, pipe).trim();
    const value = text.slice(pipe + 1).trim();

    return {
      value: value !== "" ? value : label,
      label: label !== "" ? label : value,
    };
  }

  return { value: text, label: text };
}

/** Normalize legacy array options (`["Label|CODE"]`) and object options into one record. */
export function fieldOptionsToRecord(field: EApprovalFormFieldInput): Record<string, unknown> {
  const raw = field.options;
  if (Array.isArray(raw)) {
    const choices = parseSelectChoices(field);
    if (choices.length === 0) {
      return {};
    }

    return { choices };
  }

  if (raw && typeof raw === "object") {
    return { ...(raw as Record<string, unknown>) };
  }

  return {};
}

/** Merge option patches without dropping `layout`, `choices`, or other keys. */
export function mergeFieldOptions(
  field: EApprovalFormFieldInput,
  patch: Record<string, unknown>,
): Record<string, unknown> {
  const merged = { ...fieldOptionsToRecord(field), ...patch };
  for (const [key, value] of Object.entries(patch)) {
    if (value === undefined) {
      delete merged[key];
    }
  }

  return merged;
}

/** Convert legacy pipe-string array options to `{ choices: [...] }` for the builder. */
export function normalizeFieldOptionsShape(field: EApprovalFormFieldInput): EApprovalFormFieldInput {
  if (!Array.isArray(field.options)) {
    return field;
  }

  const record = fieldOptionsToRecord(field);

  return { ...field, options: record };
}

/** Master data set key when select options are loaded at runtime. */
export function getMasterDataLookupKey(field: EApprovalFormFieldInput): string | null {
  const raw = field.options;
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) {
    return null;
  }

  const record = raw as Record<string, unknown>;
  const key =
    record.master_data_key ??
    record.masterDataKey ??
    record.lookup_key ??
    record.lookupKey;

  return typeof key === "string" && key.trim() !== "" ? key.trim() : null;
}

function normalizeGridColumnDef(entry: unknown, index: number): GridColumnDef | null {
  if (typeof entry === "string") {
    const label = entry.trim();
    if (label === "") {
      return null;
    }

    return { label, type: "text" };
  }

  if (!entry || typeof entry !== "object" || Array.isArray(entry)) {
    return null;
  }

  const record = entry as Record<string, unknown>;
  const label = String(record.label ?? record.name ?? "").trim();
  if (label === "") {
    return null;
  }

  const typeRaw = String(record.type ?? "text").trim().toLowerCase();
  const type = (["text", "number", "currency", "date", "select"] as const).includes(typeRaw as GridColumnType)
    ? (typeRaw as GridColumnType)
    : "text";

  const masterKey =
    record.master_data_key ?? record.masterDataKey ?? record.lookup_key ?? record.lookupKey;
  const master_data_key =
    typeof masterKey === "string" && masterKey.trim() !== "" ? masterKey.trim() : undefined;

  let choices: SelectChoice[] | undefined;
  if (Array.isArray(record.choices)) {
    choices = record.choices
      .map(parseChoiceEntry)
      .filter((c): c is SelectChoice => c !== null);
  }

  return {
    label,
    type,
    ...(master_data_key ? { master_data_key } : {}),
    ...(choices && choices.length > 0 ? { choices } : {}),
  };
}

/** Full grid column definitions (labels + cell field types). */
export function parseGridColumnDefs(field: EApprovalFormFieldInput): GridColumnDef[] {
  const raw = field.options;
  if (Array.isArray(raw)) {
    return raw
      .map((entry, index) => normalizeGridColumnDef(entry, index))
      .filter((c): c is GridColumnDef => c !== null);
  }

  if (raw && typeof raw === "object" && !Array.isArray(raw)) {
    const columns = (raw as { columns?: unknown }).columns;
    if (Array.isArray(columns)) {
      return columns
        .map((entry, index) => normalizeGridColumnDef(entry, index))
        .filter((c): c is GridColumnDef => c !== null);
    }
  }

  return [];
}

/** Column header labels (backward compatible). */
export function parseGridColumns(field: EApprovalFormFieldInput): string[] {
  return parseGridColumnDefs(field).map((c) => c.label);
}

export function setGridColumnDefs(columns: GridColumnDef[]): { columns: GridColumnDef[] } {
  return {
    columns: columns
      .map((col) => ({
        label: col.label.trim(),
        type: col.type,
        ...(col.master_data_key ? { master_data_key: col.master_data_key.trim() } : {}),
        ...(col.choices && col.choices.length > 0 ? { choices: col.choices } : {}),
      }))
      .filter((col) => col.label !== ""),
  };
}

/** @deprecated Prefer setGridColumnDefs — keeps legacy string[] storage. */
export function setGridColumns(columns: string[]): string[] {
  return columns.map((c) => c.trim()).filter((c) => c !== "");
}

/** Build a synthetic select field for grid column option loading. */
export function gridColumnAsSelectField(column: GridColumnDef): EApprovalFormFieldInput {
  if (column.master_data_key) {
    return {
      type: "select",
      name: "_grid_column",
      label: column.label,
      options: { master_data_key: column.master_data_key },
    };
  }

  return {
    type: "select",
    name: "_grid_column",
    label: column.label,
    options: { choices: column.choices ?? [] },
  };
}

export type GridFieldValue = { rows: Record<string, string>[] };

export function emptyGridValue(columnCount: number): GridFieldValue {
  const row: Record<string, string> = {};
  for (let i = 0; i < columnCount; i += 1) {
    row[String(i)] = "";
  }

  return { rows: columnCount > 0 ? [row] : [] };
}

export function parseGridValue(raw: string, columnCount: number, columnLabels: string[] = []): GridFieldValue {
  if (!raw.trim()) {
    return emptyGridValue(columnCount);
  }

  try {
    const parsed = JSON.parse(raw) as unknown;
    if (Array.isArray(parsed)) {
      return {
        rows: parsed.map((row) => normalizeGridRow(row, columnCount, columnLabels)),
      };
    }
    if (parsed && typeof parsed === "object" && Array.isArray((parsed as GridFieldValue).rows)) {
      return {
        rows: (parsed as GridFieldValue).rows.map((row) => normalizeGridRow(row, columnCount, columnLabels)),
      };
    }
  } catch {
    /* fall through */
  }

  return emptyGridValue(columnCount);
}

function normalizeGridRow(row: unknown, columnCount: number, columnLabels: string[] = []): Record<string, string> {
  const base = emptyGridValue(columnCount).rows[0] ?? {};
  if (!row || typeof row !== "object" || Array.isArray(row)) {
    return { ...base };
  }

  const record = row as Record<string, unknown>;
  const next = { ...base };
  const labelIndex = new Map<string, number>();
  columnLabels.forEach((label, index) => {
    const trimmed = label.trim();
    if (trimmed !== "") {
      labelIndex.set(trimmed.toLowerCase(), index);
    }
  });

  for (let i = 0; i < columnCount; i += 1) {
    const key = String(i);
    const slug = columnKey(i, columnCount);
    let val = record[key] ?? record[slug];

    if ((val === undefined || val === null) && columnLabels[i]) {
      val = record[columnLabels[i]] ?? record[columnLabels[i].toLowerCase()];
    }

    if (val === undefined || val === null) {
      for (const [rawKey, rawVal] of Object.entries(record)) {
        if (rawVal === undefined || rawVal === null || typeof rawKey !== "string") {
          continue;
        }

        const mappedIndex = labelIndex.get(rawKey.trim().toLowerCase());
        if (mappedIndex === i) {
          val = rawVal;
          break;
        }
      }
    }

    if (val !== undefined && val !== null) {
      next[key] = String(val);
    }
  }

  return next;
}

/** Normalize labeled backend grid JSON into indexed `{ rows: [{ "0": … }] }` for the editor. */
export function normalizeGridFieldValue(raw: string, field: EApprovalFormFieldInput): string {
  const columns = parseGridColumnDefs(field);
  const columnCount = Math.max(columns.length, 1);
  const columnLabels = columns.map((column) => column.label);

  return serializeGridValue(parseGridValue(raw, columnCount, columnLabels));
}

export function normalizeComposeInitialValues(
  fields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): Record<string, string> {
  const next = { ...values };

  for (const field of fields) {
    if (field.type !== "grid" || next[field.name] === undefined) {
      continue;
    }

    next[field.name] = normalizeGridFieldValue(next[field.name], field);
  }

  return next;
}

export function serializeGridValue(value: GridFieldValue): string {
  return JSON.stringify(value);
}

export function columnKey(index: number, _columnCount: number): string {
  return String(index);
}

export function resolveFieldDisplayLabel(field: EApprovalFormFieldInput): string {
  const label = field.label?.trim() ?? "";
  if (label && label !== "." && label !== "—") {
    return label;
  }

  if (field.type === "grid") {
    return "Line items";
  }

  return field.name;
}

export function gridHasContent(value: GridFieldValue): boolean {
  return value.rows.some((row) =>
    Object.values(row).some((cell) => cell.trim() !== ""),
  );
}

/** JSON text for the form builder options editor (preserves legacy shapes). */
export function optionsToEditorJson(field: EApprovalFormFieldInput): string {
  const raw = field.options;

  if (field.type === "grid") {
    return JSON.stringify(parseGridColumnDefs(field), null, 2);
  }

  if (field.type === "select" || field.type === "radio") {
    if (Array.isArray(raw)) {
      return JSON.stringify(raw, null, 2);
    }
    if (raw && typeof raw === "object" && Array.isArray((raw as { choices?: unknown }).choices)) {
      return JSON.stringify((raw as { choices: unknown[] }).choices, null, 2);
    }

    return "[]";
  }

  if (raw === null || raw === undefined) {
    return "{}";
  }

  return JSON.stringify(raw, null, 2);
}

/** Parse form builder options JSON back into stored field.options. */
export function optionsFromEditorJson(
  fieldType: string,
  text: string,
): EApprovalFormFieldInput["options"] {
  const trimmed = text.trim();
  if (trimmed === "") {
    return fieldType === "grid" ? [] : {};
  }

  const parsed: unknown = JSON.parse(trimmed);

  if (fieldType === "grid") {
    if (Array.isArray(parsed)) {
      const defs = parsed
        .map((entry, index) => normalizeGridColumnDef(entry, index))
        .filter((c): c is GridColumnDef => c !== null);

      return setGridColumnDefs(defs);
    }
    if (parsed && typeof parsed === "object" && Array.isArray((parsed as { columns?: unknown }).columns)) {
      const defs = (parsed as { columns: unknown[] }).columns
        .map((entry, index) => normalizeGridColumnDef(entry, index))
        .filter((c): c is GridColumnDef => c !== null);

      return setGridColumnDefs(defs);
    }

    return { columns: [] };
  }

  if (fieldType === "select" || fieldType === "radio") {
    if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
      const record = parsed as Record<string, unknown>;
      const masterKey =
        record.master_data_key ?? record.masterDataKey ?? record.lookup_key ?? record.lookupKey;
      if (typeof masterKey === "string" && masterKey.trim() !== "") {
        return { master_data_key: masterKey.trim() };
      }
      if (Array.isArray(record.choices)) {
        return { choices: record.choices };
      }
    }

    if (!Array.isArray(parsed)) {
      return { choices: [] };
    }

    if (parsed.every((entry) => typeof entry === "string")) {
      return parsed;
    }

    return { choices: parsed };
  }

  if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
    return parsed as Record<string, unknown>;
  }

  return {};
}
