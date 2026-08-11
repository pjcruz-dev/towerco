"use client";

import { useCallback, useMemo, useState } from "react";

import type { RolloutListRow } from "@/modules/rollout/types";

export function isRolloutBulkSelectable(row: RolloutListRow): boolean {
  return !row.is_batch && row.status !== "completed" && row.status !== "cancelled";
}

export function useRolloutBulkSelection(rows: RolloutListRow[]) {
  const [selectedIds, setSelectedIds] = useState<Set<string>>(() => new Set());

  const selectableRows = useMemo(() => rows.filter(isRolloutBulkSelectable), [rows]);

  const selectableIds = useMemo(() => selectableRows.map((row) => row.id), [selectableRows]);

  const allPageSelected =
    selectableIds.length > 0 && selectableIds.every((id) => selectedIds.has(id));

  const somePageSelected = selectableIds.some((id) => selectedIds.has(id));

  const toggle = useCallback((id: string) => {
    setSelectedIds((current) => {
      const next = new Set(current);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  const toggleAllOnPage = useCallback(() => {
    setSelectedIds((current) => {
      const next = new Set(current);
      if (allPageSelected) {
        for (const id of selectableIds) {
          next.delete(id);
        }
      } else {
        for (const id of selectableIds) {
          next.add(id);
        }
      }
      return next;
    });
  }, [allPageSelected, selectableIds]);

  const clear = useCallback(() => {
    setSelectedIds(new Set());
  }, []);

  const selectedRows = useMemo(
    () => rows.filter((row) => selectedIds.has(row.id)),
    [rows, selectedIds],
  );

  const replaceSelection = useCallback((next: Set<string>) => {
    setSelectedIds(next);
  }, []);

  return {
    selectedIds,
    selectedCount: selectedIds.size,
    selectedRows,
    selectableIds,
    allPageSelected,
    somePageSelected,
    toggle,
    toggleAllOnPage,
    clear,
    replaceSelection,
    isSelected: (id: string) => selectedIds.has(id),
  };
}
