import type { OnChangeFn, SortingState } from "@tanstack/react-table";

/**
 * Parse API `field:dir` into TanStack SortingState.
 * When API field names differ from column ids, pass `apiFieldToColumnId`.
 * When the active sort is not a visible/sortable column, pass `sortableColumnIds`
 * so the header state stays empty (API still uses the default sort).
 */
export function sortingStateFromSortParam(
  sort: string,
  apiFieldToColumnId: Record<string, string> = {},
  sortableColumnIds?: readonly string[],
): SortingState {
  const [field, direction] = sort.split(":");
  if (!field) return [];
  const id = apiFieldToColumnId[field] ?? field;
  if (sortableColumnIds && !sortableColumnIds.includes(id)) return [];
  return [{ id, desc: direction === "desc" }];
}

/**
 * Map TanStack SortingState back to API `field:dir`.
 * When column ids differ from API fields, pass `columnIdToApiField`.
 */
export function sortParamFromSortingState(
  sorting: SortingState,
  defaultSort: string,
  columnIdToApiField: Record<string, string> = {},
): string {
  const first = sorting[0];
  if (!first) return defaultSort;
  const field = columnIdToApiField[first.id] ?? first.id;
  return `${field}:${first.desc ? "desc" : "asc"}`;
}

export function createSortingChangeHandler(
  sorting: SortingState,
  setSort: (sort: string) => void,
  defaultSort: string,
  columnIdToApiField: Record<string, string> = {},
): OnChangeFn<SortingState> {
  return (updater) => {
    const next = typeof updater === "function" ? updater(sorting) : updater;
    setSort(sortParamFromSortingState(next, defaultSort, columnIdToApiField));
  };
}
