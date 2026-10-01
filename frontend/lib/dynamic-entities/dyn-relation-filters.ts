import type { DynField, DynRelationFilter, DynRelationFilterOp } from "@/lib/api/modules/dynamic-entities-api";

export const DYN_RELATION_FILTER_OPS: Array<{ value: DynRelationFilterOp; label: string }> = [
  { value: "eq", label: "is" },
  { value: "neq", label: "is not" },
  { value: "gt", label: "is greater than" },
  { value: "lt", label: "is less than" },
  { value: "contains", label: "contains" },
  { value: "in", label: "is any of" },
];

const ALLOWED_OPS = new Set<string>(DYN_RELATION_FILTER_OPS.map((o) => o.value));

function normalizeRelationFilterOp(raw: unknown): DynRelationFilterOp {
  const op = String(raw ?? "").trim().toLowerCase();
  if (ALLOWED_OPS.has(op)) {
    return op as DynRelationFilterOp;
  }
  // Legacy / Metacoresoft aliases
  if (op === "equals" || op === "=") return "eq";
  if (op === "not_equals" || op === "ne" || op === "!=") return "neq";
  if (op === "greater_than" || op === ">") return "gt";
  if (op === "less_than" || op === "<") return "lt";
  if (op === "any_of" || op === "in_list") return "in";

  return "eq";
}

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
      return {
        field,
        op: normalizeRelationFilterOp(r.op),
        value: String(r.value ?? ""),
      };
    })
    .filter((r): r is DynRelationFilter => r !== null);
}

/**
 * Build nested `filter` query params for DynRecordIndex / DynRecordQueryFilterParser.
 * eq → filter[field][eq], neq → filter[field][neq], etc.
 */
export function relationFiltersToRecordFilterParams(
  filters: DynRelationFilter[],
): Record<string, Record<string, string>> {
  const out: Record<string, Record<string, string>> = {};
  for (const rule of filters) {
    const field = rule.field.trim();
    if (!field) continue;
    const op = normalizeRelationFilterOp(rule.op);
    out[field] = { ...(out[field] ?? {}), [op]: rule.value };
  }
  return out;
}

export function relationFiltersFromField(field: DynField): DynRelationFilter[] {
  return parseDynRelationFilters(field.options);
}

/** Field choices for Link "Limit the choices" — target entity schema + id/status. */
export function relationFilterFieldOptions(
  fields: DynField[],
): Array<{ name: string; label: string }> {
  const builtins: Array<{ name: string; label: string }> = [
    { name: "id", label: "ID" },
    { name: "status", label: "Status" },
    { name: "title", label: "Title" },
  ];
  const fromEntity = fields
    .filter((f) => !f.is_system_field || f.name === "status" || f.name === "id")
    .filter((f) => !["actions", "workflows", "print"].includes(f.name))
    .map((f) => ({ name: f.name, label: f.label || f.name }));

  const seen = new Set<string>();
  const out: Array<{ name: string; label: string }> = [];
  for (const row of [...builtins, ...fromEntity]) {
    if (seen.has(row.name)) continue;
    seen.add(row.name);
    out.push(row);
  }
  return out;
}
