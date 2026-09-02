import type { DynField } from "@/lib/api/modules/dynamic-entities-api";
import { formatDynNumberValue } from "@/lib/dynamic-entities/number-options";

const CURRENCY_NAME_RE =
  /(cost|budget|amount|price|rent|fee|deposit|depreciation|salary|payment|total|value)/i;
const YEAR_NAME_RE = /(^|_)year(_|$)|fiscal_year|budget_year/i;

export function isCurrencyField(field?: Pick<DynField, "name" | "type" | "label"> | null): boolean {
  if (!field) return false;
  if (field.type !== "number" && field.type !== "decimal") return false;
  return CURRENCY_NAME_RE.test(field.name) || CURRENCY_NAME_RE.test(field.label ?? "");
}

export function isYearField(field?: Pick<DynField, "name" | "type" | "label"> | null): boolean {
  if (!field) return false;
  if (field.type !== "number" && field.type !== "decimal") return false;
  return YEAR_NAME_RE.test(field.name) || YEAR_NAME_RE.test(field.label ?? "");
}

export function formatDynListCell(
  value: unknown,
  field?: Pick<DynField, "name" | "type" | "label" | "options"> | null,
): string {
  if (value === null || value === undefined || value === "") return "—";
  if (typeof value === "object") return JSON.stringify(value);

  if (isYearField(field) && typeof value === "number") {
    return String(Math.trunc(value));
  }
  if (isYearField(field) && typeof value === "string" && /^-?\d+(\.0+)?$/.test(value.trim())) {
    return String(Math.trunc(Number(value)));
  }

  if (field?.type === "number" || field?.type === "decimal") {
    const formatted = formatDynNumberValue(value, field.options, isCurrencyField(field));
    if (formatted !== null) return formatted;
  }

  return String(value);
}

export function dynStatusTone(text: string): string {
  const t = text.toLowerCase();
  if (t.includes("active") || t.includes("complete") || t.includes("posted") || t === "rfti") {
    return "bg-emerald-500/15 text-emerald-700 dark:text-emerald-400";
  }
  if (t.includes("wip") || t.includes("pending") || t.includes("progress") || t.includes("planning")) {
    return "bg-amber-500/15 text-amber-800 dark:text-amber-400";
  }
  if (t.includes("macro")) return "bg-sky-500/15 text-sky-800 dark:text-sky-400";
  if (t.includes("small")) return "bg-teal-500/15 text-teal-800 dark:text-teal-400";
  if (t.includes("cancel") || t.includes("void") || t.includes("over")) {
    return "bg-red-500/15 text-red-700 dark:text-red-400";
  }
  return "bg-slate-500/15 text-slate-700 dark:text-slate-300";
}
