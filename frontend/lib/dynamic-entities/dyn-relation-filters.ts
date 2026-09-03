import type { DynField, DynRelationFilter } from "@/lib/api/modules/dynamic-entities-api";

/** Parse relationship picker filters from field.options.filters (Manage Fields schema). */
export function parseDynRelationFilters(options: unknown): DynRelationFilter[] {
  if (!options || typeof options !== "object" || Array.isArray(options)) {
    return [];
  }
  const filters = (options as { filters?: unknown }).filters;
  if (!Array.isArray(filters)) {
    return [];
  }
  return filters
    .map((row): DynRelationFilter | null => {
      if (!row || typeof row !== "object") return null;
      const r = row as Record<string, unknown>;
      const field = String(r.field ?? "").trim();
      if (!field) return null;
      const op = (["eq", "neq", "contains"].includes(String(r.op))
        ? String(r.op)
        : "eq") as DynRelationFilter["op"];
      return { field, op, value: String(r.value ?? "") };
    })
    .filter((r): r is DynRelationFilter => r !== null);
}

/**
 * Build nested `filter` query params for DynRecordIndex / DynRecordQueryFilterParser.
 * eq → filter[field][eq], neq → filter[field][neq], contains → filter[field][contains]
 */
export function relationFiltersToRecordFilterParams(
  filters: DynRelationFilter[],
): Record<string, Record<string, string>> {
  const out: Record<string, Record<string, string>> = {};
  for (const rule of filters) {
    const field = rule.field.trim();
    if (!field) continue;
    const op =
      rule.op === "neq" ? "neq" : rule.op === "contains" ? "contains" : "eq";
    out[field] = { ...(out[field] ?? {}), [op]: rule.value };
  }
  return out;
}

export function relationFiltersFromField(field: DynField): DynRelationFilter[] {
  return parseDynRelationFilters(field.options);
}
