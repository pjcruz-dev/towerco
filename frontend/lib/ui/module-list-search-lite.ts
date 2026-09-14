/**
 * Lightweight search tokens with optional operators:
 *   status:ready   / status=ready  → equality filter (UI + stripped from search)
 *   status:pending|approved → left in search for backend OR DSL
 *   title~outage / status!=closed / created>=2026-01-01 → left in search for backend DSL
 *   status:open OR priority:high → left intact for backend cross-key OR
 */

export type SearchLiteAllowlist = Record<string, readonly string[] | "*">;

export type SearchLiteResult = {
  /** Residual free-text after removing recognized equality tokens (may still include operators). */
  search: string;
  /** Parsed equality filter values (first match wins per key). */
  filters: Record<string, string>;
  /** Operator used per recognized key. */
  operators: Record<string, "eq" | "ne" | "contains" | "gte" | "lte" | "gt" | "lt">;
};

const TOKEN_RE =
  /(?:^|\s)([a-z_][a-z0-9_]*)\s*(!=|>=|<=|:|=|~|>|<)\s*("([^"]*)"|([^\s]+))/gi;

function normalizeOperator(raw: string): SearchLiteResult["operators"][string] {
  if (raw === "!=") return "ne";
  if (raw === "~") return "contains";
  if (raw === ">=") return "gte";
  if (raw === "<=") return "lte";
  if (raw === ">") return "gt";
  if (raw === "<") return "lt";
  return "eq";
}

/** True when a top-level (outside quotes) ` OR ` separator is present. */
export function hasTopLevelSearchOr(raw: string): boolean {
  let inQuotes = false;
  for (let i = 0; i < raw.length; i++) {
    const ch = raw[i];
    if (ch === '"') {
      inQuotes = !inQuotes;
      continue;
    }
    if (inQuotes) continue;
    if (/\s/.test(ch) && /^(\s+OR\s+)/i.test(raw.slice(i))) {
      return true;
    }
  }
  return false;
}

export function parseModuleListSearchLite(
  raw: string,
  allowlist: SearchLiteAllowlist,
): SearchLiteResult {
  // Cross-key OR must stay intact for backend ModuleListSearchDsl.
  if (hasTopLevelSearchOr(raw)) {
    return {
      search: raw.replace(/\s+/g, " ").trim(),
      filters: {},
      operators: {},
    };
  }

  const filters: Record<string, string> = {};
  const operators: SearchLiteResult["operators"] = {};
  let residual = raw;

  const matches = [...raw.matchAll(TOKEN_RE)];
  for (const match of matches) {
    const key = (match[1] ?? "").toLowerCase();
    const op = normalizeOperator(match[2] ?? ":");
    const value = (match[4] ?? match[5] ?? "").trim();
    if (!key || value === "") continue;
    const allowed = allowlist[key];
    if (allowed === undefined) continue;

    if (op === "eq") {
      // Pipe OR-groups stay in search for backend ModuleListSearchDsl.
      if (value.includes("|")) {
        if (operators[key] === undefined) {
          operators[key] = "eq";
        }
        continue;
      }
      if (allowed !== "*" && !allowed.some((item) => item.toLowerCase() === value.toLowerCase())) {
        continue;
      }
      if (filters[key] === undefined) {
        filters[key] = value;
        operators[key] = "eq";
      }
      residual = residual.replace(match[0], " ");
      continue;
    }

    // Non-equality operators stay in the search string for backend ModuleListSearchDsl.
    if (operators[key] === undefined) {
      operators[key] = op;
    }
  }

  return {
    search: residual.replace(/\s+/g, " ").trim(),
    filters,
    operators,
  };
}
