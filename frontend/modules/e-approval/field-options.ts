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
  data?: {
    address?: string;
    vatable?: boolean;
    per_diem?: boolean;
    hotel?: boolean;
  };
};

export type GridColumnType =
  | "text"
  | "textarea"
  | "number"
  | "currency"
  | "date"
  | "date_range"
  | "select"
  | "email"
  | "phone"
  | "boolean";

export type GridColumnDef = {
  label: string;
  type: GridColumnType;
  master_data_key?: string;
  choices?: SelectChoice[];
  /** Copy the paired column's site address into this cell. */
  filled_from?: string;
  /** Select options come from another grid on the same form (row number). */
  linked_grid?: string;
  /** Date range columns include a clock. Missing means on, so existing forms keep time. */
  include_time?: boolean;
  /** Hidden columns stay in the saved grid so existing cell indexes do not move. */
  hidden?: boolean;
  /** The requestor must fill this cell on any line that has other values. */
  required?: boolean;
  /** A site lookup can hold more than one site. */
  multiple?: boolean;
  /** This number or currency column is added to the grid's total row. */
  show_total?: boolean;
  /**
   * Site lookup columns to write onto the line. Missing means address only.
   * An empty list keeps the site ID and nothing else.
   */
  show_fields?: string[];
  locked?: boolean;
  /** `today` blocks past days. `lead` blocks before today + lead_days. `field` blocks before that field's date. */
  date_min?: "today" | "lead" | "field";
  lead_days?: number;
  date_min_field?: string;
  /** Site cells must belong to this form field (usually Subsidiary). */
  subsidiary_field?: string;
};

export const GRID_COLUMN_TYPE_LABELS: Record<GridColumnType, string> = {
  text: "Short text",
  textarea: "Long text",
  number: "Number",
  currency: "Currency",
  date: "Date",
  date_range: "Date range",
  select: "Dropdown list",
  email: "Email",
  phone: "Phone",
  boolean: "Yes / No",
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
      data?: unknown;
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
    if (row.data && typeof row.data === "object" && !Array.isArray(row.data)) {
      const dataRecord = row.data as Record<string, unknown>;
      const data: NonNullable<SelectChoice["data"]> = {};
      if (typeof dataRecord.address === "string" && dataRecord.address.trim() !== "") {
        data.address = dataRecord.address.trim();
      }
      if (typeof dataRecord.vatable === "boolean") {
        data.vatable = dataRecord.vatable;
      }
      if (typeof dataRecord.per_diem === "boolean") {
        data.per_diem = dataRecord.per_diem;
      }
      if (typeof dataRecord.hotel === "boolean") {
        data.hotel = dataRecord.hotel;
      }
      if (Object.keys(data).length > 0) {
        choice.data = data;
      }
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
  const type = (
    ["text", "textarea", "number", "currency", "date", "date_range", "select", "email", "phone", "boolean"] as const
  ).includes(typeRaw as GridColumnType)
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

  const filledFrom = typeof record.filled_from === "string" ? record.filled_from.trim() : "";
  const linkedGrid = typeof record.linked_grid === "string" ? record.linked_grid.trim() : "";
  const showFields = Array.isArray(record.show_fields)
    ? record.show_fields
        .map((key) => (typeof key === "string" ? key.trim() : ""))
        .filter((key) => key !== "")
    : undefined;

  return {
    label,
    type,
    ...(master_data_key ? { master_data_key } : {}),
    ...(choices && choices.length > 0 ? { choices } : {}),
    ...(filledFrom !== "" ? { filled_from: filledFrom } : {}),
    ...(linkedGrid !== "" ? { linked_grid: linkedGrid } : {}),
    ...(type === "date_range" && record.include_time === false ? { include_time: false } : {}),
    ...(record.hidden === true ? { hidden: true } : {}),
    ...(record.required === true ? { required: true } : {}),
    ...(record.multiple === true ? { multiple: true } : {}),
    ...(record.show_total === true ? { show_total: true } : {}),
    ...(showFields ? { show_fields: showFields } : {}),
    ...(record.locked === true || label.toLowerCase() === "vat" ? { locked: true } : {}),
    ...(record.date_min === "today" || record.date_min === "lead" || record.date_min === "field"
      ? { date_min: record.date_min }
      : {}),
    ...(record.date_min === "lead" && typeof record.lead_days === "number"
      ? { lead_days: Math.max(1, Math.floor(record.lead_days)) }
      : {}),
    ...(record.date_min === "field" && typeof record.date_min_field === "string" && record.date_min_field.trim() !== ""
      ? { date_min_field: record.date_min_field.trim() }
      : {}),
    ...(typeof record.subsidiary_field === "string" && record.subsidiary_field.trim() !== ""
      ? { subsidiary_field: record.subsidiary_field.trim() }
      : {}),
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
        ...(col.filled_from ? { filled_from: col.filled_from } : {}),
        ...(col.linked_grid ? { linked_grid: col.linked_grid } : {}),
        ...(col.type === "date_range" && col.include_time === false ? { include_time: false } : {}),
        ...(col.hidden ? { hidden: true } : {}),
        ...(col.required ? { required: true } : {}),
        ...(col.multiple ? { multiple: true } : {}),
        ...(col.show_total ? { show_total: true } : {}),
        ...(col.show_fields ? { show_fields: col.show_fields } : {}),
        ...(col.locked ? { locked: true } : {}),
        ...(col.date_min ? { date_min: col.date_min } : {}),
        ...(col.date_min === "lead" && col.lead_days ? { lead_days: col.lead_days } : {}),
        ...(col.date_min === "field" && col.date_min_field ? { date_min_field: col.date_min_field } : {}),
        ...(col.subsidiary_field ? { subsidiary_field: col.subsidiary_field } : {}),
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
  if (column.master_data_key && !(column.choices && column.choices.length > 0)) {
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
  if (Array.isArray(row)) {
    const next = { ...base };
    for (let i = 0; i < columnCount; i += 1) {
      const cell = row[i];
      if (cell !== undefined && cell !== null) {
        next[String(i)] = String(cell);
      }
    }
    return next;
  }
  if (!row || typeof row !== "object") {
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
