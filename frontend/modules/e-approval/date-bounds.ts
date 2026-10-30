import { gridHasContent, parseGridColumnDefs, parseGridValue, type GridColumnDef } from "@/modules/e-approval/field-options";
import { parseDateRangeValue } from "@/modules/e-approval/field-type-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export type DateBoundKind = "none" | "today" | "lead" | "field";

export type DateBound = {
  kind: DateBoundKind;
  leadDays?: number;
  field?: string;
};

function asRecord(source: unknown): Record<string, unknown> | null {
  if (!source || typeof source !== "object" || Array.isArray(source)) {
    return null;
  }
  return source as Record<string, unknown>;
}

export function parseDateBound(source: unknown): DateBound {
  const record = asRecord(source);
  const kind = record?.date_min;
  if (kind === "today") {
    return { kind: "today" };
  }
  if (kind === "lead") {
    const days = Number(record?.lead_days);
    return {
      kind: "lead",
      leadDays: Number.isFinite(days) && days > 0 ? Math.floor(days) : 3,
    };
  }
  if (kind === "field") {
    const field = typeof record?.date_min_field === "string" ? record.date_min_field.trim() : "";
    return field !== "" ? { kind: "field", field } : { kind: "none" };
  }
  return { kind: "none" };
}

export function todayIso(now = new Date()): string {
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, "0");
  const d = String(now.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

export function addCalendarDays(iso: string, days: number): string {
  const [y, m, d] = iso.split("-").map(Number);
  const date = new Date(y ?? 1970, (m ?? 1) - 1, d ?? 1);
  date.setDate(date.getDate() + days);
  return todayIso(date);
}

export function isoDateOnly(value: string): string {
  const match = value.trim().match(/^(\d{4}-\d{2}-\d{2})/);
  return match?.[1] ?? "";
}

export function earliestIsoDate(
  bound: DateBound,
  values: Record<string, string>,
  now = new Date(),
): string | null {
  if (bound.kind === "today") {
    return todayIso(now);
  }
  if (bound.kind === "lead") {
    return addCalendarDays(todayIso(now), bound.leadDays ?? 3);
  }
  if (bound.kind === "field") {
    const raw = isoDateOnly(values[bound.field ?? ""] ?? "");
    return raw !== "" ? raw : null;
  }
  return null;
}

export function isBeforeIsoDate(value: string, min: string): boolean {
  const day = isoDateOnly(value);
  return day !== "" && min !== "" && day < min;
}

export function dateBoundMessage(label: string, bound: DateBound, fields: EApprovalFormFieldInput[]): string {
  if (bound.kind === "lead") {
    return `${label} must be at least ${bound.leadDays ?? 3} days from today.`;
  }
  if (bound.kind === "today") {
    return `${label} cannot be in the past.`;
  }
  const other = fields.find((field) => field.name === bound.field);
  const otherLabel = other?.label?.trim() || "the earlier date";
  return `${label} must be on or after ${otherLabel}.`;
}

export function dateValueBreaksBound(
  raw: string,
  bound: DateBound,
  values: Record<string, string>,
  now = new Date(),
): boolean {
  const min = earliestIsoDate(bound, values, now);
  if (!min) {
    return false;
  }
  const range = parseDateRangeValue(raw);
  if (range.from !== "" || range.to !== "") {
    return isBeforeIsoDate(range.from, min) || isBeforeIsoDate(range.to, min);
  }
  return isBeforeIsoDate(raw, min);
}

function siteSubsidiaries(cell: string): string[] {
  const trimmed = cell.trim();
  if (!trimmed.startsWith("{")) {
    return [];
  }
  try {
    const parsed = JSON.parse(trimmed) as { sites?: unknown; fields?: unknown };
    const entries = Array.isArray(parsed.sites) ? parsed.sites : [parsed];
    const found: string[] = [];
    for (const entry of entries) {
      if (!entry || typeof entry !== "object") {
        continue;
      }
      const fields = (entry as { fields?: unknown }).fields;
      if (!fields || typeof fields !== "object" || Array.isArray(fields)) {
        continue;
      }
      const subsidiary = String((fields as Record<string, unknown>).subsidiary ?? "").trim();
      if (subsidiary !== "") {
        found.push(subsidiary);
      }
    }
    return found;
  } catch {
    return [];
  }
}

export function gridColumnDateIssue(
  column: GridColumnDef,
  cell: string,
  values: Record<string, string>,
  fields: EApprovalFormFieldInput[],
): string | null {
  if (column.hidden || (column.type !== "date" && column.type !== "date_range")) {
    return null;
  }
  const bound = parseDateBound(column);
  if (bound.kind === "none" || cell.trim() === "") {
    return null;
  }
  if (!dateValueBreaksBound(cell, bound, values)) {
    return null;
  }
  return dateBoundMessage(column.label, bound, fields);
}

export function gridColumnSubsidiaryIssue(
  column: GridColumnDef,
  cell: string,
  values: Record<string, string>,
): string | null {
  const matchField = column.subsidiary_field?.trim() ?? "";
  if (matchField === "" || column.hidden || cell.trim() === "") {
    return null;
  }
  const expected = (values[matchField] ?? "").trim();
  if (expected === "") {
    return null;
  }
  const mismatch = siteSubsidiaries(cell).some(
    (subsidiary) => subsidiary.toUpperCase() !== expected.toUpperCase(),
  );
  if (!mismatch) {
    return null;
  }
  return `${column.label} must belong to ${expected}.`;
}

export function fieldLockedByFilledGrid(
  field: EApprovalFormFieldInput,
  allFields: EApprovalFormFieldInput[],
  values: Record<string, string>,
): boolean {
  const options = asRecord(field.options);
  const gridName = typeof options?.lock_when_grid === "string" ? options.lock_when_grid.trim() : "";
  if (gridName === "") {
    return false;
  }
  const grid = allFields.find((candidate) => candidate.type === "grid" && candidate.name === gridName);
  const width = grid ? Math.max(parseGridColumnDefs(grid).length, 1) : 1;
  return gridHasContent(parseGridValue(values[gridName] ?? "", width));
}
