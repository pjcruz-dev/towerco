import type { OperationalAcronym, OperationalAcronymMap } from "@/lib/operational-acronyms/types";

export function buildAcronymMap(rows: OperationalAcronym[]): OperationalAcronymMap {
  const map: OperationalAcronymMap = {};

  for (const row of rows) {
    const key = normalizeAcronymKey(row.acronym);
    if (key) {
      map[key] = row;
    }
  }

  return map;
}

export function normalizeAcronymKey(value: string): string {
  return value.trim().replace(/\s+/g, " ");
}

export function lookupAcronym(map: OperationalAcronymMap, token: string): OperationalAcronym | null {
  const key = normalizeAcronymKey(token);
  if (!key) {
    return null;
  }

  const direct = map[key];
  if (direct) {
    return direct;
  }

  const upper = key.toUpperCase();
  for (const [entryKey, row] of Object.entries(map)) {
    if (entryKey.toUpperCase() === upper) {
      return row;
    }
  }

  return null;
}

export function sortedAcronymKeys(map: OperationalAcronymMap): string[] {
  return Object.keys(map).sort((a, b) => b.length - a.length);
}

export function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}
