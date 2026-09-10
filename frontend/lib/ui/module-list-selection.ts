import type { OnChangeFn, RowSelectionState } from "@tanstack/react-table";

/** Soft ceiling aligned with sync export caps (controllers enforce the same). */
export const MODULE_LIST_EXPORT_IDS_MAX = 500;

export function selectedIdsToRowSelection(selectedIds: Set<string>): RowSelectionState {
  const next: RowSelectionState = {};
  for (const id of selectedIds) {
    next[id] = true;
  }
  return next;
}

export function applyRowSelectionToSelectedIds(
  updater: Parameters<OnChangeFn<RowSelectionState>>[0],
  current: Set<string>,
  onChange: (next: Set<string>) => void,
): void {
  const currentState = selectedIdsToRowSelection(current);
  const nextState = typeof updater === "function" ? updater(currentState) : updater;
  onChange(new Set(Object.keys(nextState).filter((id) => nextState[id])));
}

export function selectedIdsList(selectedIds: Set<string>, max = MODULE_LIST_EXPORT_IDS_MAX): string[] {
  return [...selectedIds].slice(0, max);
}
