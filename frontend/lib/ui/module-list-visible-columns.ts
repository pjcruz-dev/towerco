/**
 * Map table column visibility/order to server export column keys.
 * Prefer live ids from RegistryDataTableView when available; otherwise localStorage.
 */

export type ColumnExportMap = Record<string, string | string[] | null>;

export type MapVisibleExportColumnsOptions = {
  /** Table column id → export key(s). `null` skips (e.g. actions). */
  map?: ColumnExportMap;
  /** When set, only these export keys are emitted (after mapping). */
  allowlist?: readonly string[];
};

function expandMappedId(id: string, map?: ColumnExportMap): string[] {
  if (map && Object.prototype.hasOwnProperty.call(map, id)) {
    const value = map[id];
    if (value == null) return [];
    return Array.isArray(value) ? value : [value];
  }
  if (id === "actions" || id === "open" || id === "select") return [];
  return [id];
}

/**
 * Build ordered export keys from currently visible table column ids (live table state).
 */
export function mapVisibleExportColumns(
  visibleColumnIds: string[],
  options?: MapVisibleExportColumnsOptions,
): string[] | undefined {
  const mapped: string[] = [];
  for (const id of visibleColumnIds) {
    mapped.push(...expandMappedId(id, options?.map));
  }
  const unique = [...new Set(mapped.filter(Boolean))];
  const filtered = options?.allowlist
    ? unique.filter((key) => options.allowlist!.includes(key))
    : unique;
  return filtered.length > 0 ? filtered : undefined;
}

function readJson<T>(key: string): T | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = window.localStorage.getItem(key);
    if (!raw) return null;
    return JSON.parse(raw) as T;
  } catch {
    return null;
  }
}

/**
 * Fallback when export runs outside the table (e.g. gallery mode header toolbar).
 * Honors `{storageKey}.order` when present.
 */
export function readVisibleExportColumnsFromStorage(
  storageKey: string,
  defaultColumnIds: readonly string[],
  options?: MapVisibleExportColumnsOptions,
): string[] | undefined {
  const visibility = readJson<Record<string, boolean>>(storageKey) ?? {};
  const order = readJson<string[]>(`${storageKey}.order`);
  const orderedIds =
    Array.isArray(order) && order.length > 0
      ? [
          ...order.filter((id) => defaultColumnIds.includes(id)),
          ...defaultColumnIds.filter((id) => !order.includes(id)),
        ]
      : [...defaultColumnIds];

  const visibleIds = orderedIds.filter((id) => visibility[id] !== false);
  return mapVisibleExportColumns(visibleIds, options);
}
