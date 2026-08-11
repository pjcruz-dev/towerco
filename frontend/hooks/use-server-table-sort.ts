"use client";

import { useCallback, useMemo, useState } from "react";
import type { OnChangeFn, SortingState } from "@tanstack/react-table";

import {
  createSortingChangeHandler,
  sortingStateFromSortParam,
} from "@/lib/table/server-sort";

const EMPTY_MAP: Record<string, string> = {};

type UseServerTableSortOptions = {
  defaultSort: string;
  /** API field → column id (only when they differ) */
  apiFieldToColumnId?: Record<string, string>;
  /** Column id → API field (only when they differ) */
  columnIdToApiField?: Record<string, string>;
  /** Column ids that show a sort header; others keep API default without header state */
  sortableColumnIds?: readonly string[];
};

export function useServerTableSort(options: UseServerTableSortOptions) {
  const {
    defaultSort,
    apiFieldToColumnId = EMPTY_MAP,
    columnIdToApiField = EMPTY_MAP,
    sortableColumnIds,
  } = options;
  const [sort, setSort] = useState(defaultSort);

  const sorting = useMemo(
    () => sortingStateFromSortParam(sort, apiFieldToColumnId, sortableColumnIds),
    [sort, apiFieldToColumnId, sortableColumnIds],
  );

  const onSortingChange = useCallback<OnChangeFn<SortingState>>(
    (updater) => createSortingChangeHandler(sorting, setSort, defaultSort, columnIdToApiField)(updater),
    [sorting, defaultSort, columnIdToApiField],
  );

  return {
    sort,
    setSort,
    sorting,
    onSortingChange,
    manualSorting: true as const,
  };
}
