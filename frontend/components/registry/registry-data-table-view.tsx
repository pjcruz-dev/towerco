"use client";

import {
  getCoreRowModel,
  getSortedRowModel,
  useReactTable,
  type ColumnDef,
  type ColumnOrderState,
  type OnChangeFn,
  type Row,
  type RowSelectionState,
  type SortingState,
  type VisibilityState,
} from "@tanstack/react-table";
import type { ReactNode } from "react";
import { useCallback, useState } from "react";

import { DataTable } from "@/components/ui/data-table";
import { DataTableViewOptions } from "@/components/ui/data-table-view-options";
import { ModuleListLayoutMenu } from "@/components/registry/module-list-layout-menu";
import { RegistryTableScroll } from "@/components/registry/registry-data-table";
import {
  isVisibilityState,
  useLocalStorageJsonState,
} from "@/hooks/use-local-storage-json-state";
import { layoutsStorageKey } from "@/lib/ui/module-list-layouts";
import { cn } from "@/lib/utils";

function isColumnOrderState(value: unknown): value is ColumnOrderState {
  return Array.isArray(value) && value.every((item) => typeof item === "string");
}

type RegistryDataTableViewProps<TData> = {
  columns: ColumnDef<TData, unknown>[];
  data: TData[];
  getRowId?: (row: TData) => string;
  isLoading?: boolean;
  isEmpty?: boolean;
  emptyMessage?: string;
  emptyContent?: ReactNode;
  loadingRowCount?: number;
  getRowClassName?: (row: Row<TData>) => string | undefined;
  onRowClick?: (row: Row<TData>) => void;
  scrollClassName?: string;
  rowSelection?: RowSelectionState;
  onRowSelectionChange?: OnChangeFn<RowSelectionState>;
  enableRowSelection?: boolean | ((row: Row<TData>) => boolean);
  enableMultiRowSelection?: boolean;
  sorting?: SortingState;
  onSortingChange?: OnChangeFn<SortingState>;
  /**
   * When true (default), parent owns server sort. When false, client-sort the current page.
   */
  manualSorting?: boolean;
  /** Show Columns toggle; manage visibility internally unless controlled. */
  enableColumnVisibility?: boolean;
  columnVisibility?: VisibilityState;
  onColumnVisibilityChange?: OnChangeFn<VisibilityState>;
  /**
   * Persist column visibility to localStorage when visibility is uncontrolled.
   * Ignored when `columnVisibility` / `onColumnVisibilityChange` are provided.
   * Convention: `toweros.table.columns.<module>.<page>`
   */
  columnVisibilityStorageKey?: string;
  /** Persist column order alongside visibility (key + `.order`). */
  enableColumnReorder?: boolean;
  /** Named layouts menu (requires columnVisibilityStorageKey). */
  enableNamedLayouts?: boolean;
  toolbarStart?: ReactNode;
  /**
   * Extra toolbar actions. Pass a render function to read live visible column ids
   * (for export/print that must match the table, not only localStorage).
   */
  toolbarEnd?:
    | ReactNode
    | ((ctx: { visibleColumnIds: string[]; columnOrder: string[] }) => ReactNode);
  toolbarClassName?: string;
};

/**
 * TanStack Table + shadcn table markup with INFRA SUITE registry styling.
 * Server pagination/sorting stay in the parent; this renders the current page only.
 *
 * Prefer this for list/registry pages. Keep plain `Table` for editable grids,
 * inline-edit rows, and timeline/expand editors.
 */
export function RegistryDataTableView<TData>({
  columns,
  data,
  getRowId,
  isLoading = false,
  isEmpty = false,
  emptyMessage,
  emptyContent,
  loadingRowCount,
  getRowClassName,
  onRowClick,
  scrollClassName,
  rowSelection,
  onRowSelectionChange,
  enableRowSelection,
  enableMultiRowSelection = true,
  sorting: sortingProp,
  onSortingChange,
  manualSorting = true,
  enableColumnVisibility = false,
  columnVisibility: columnVisibilityProp,
  onColumnVisibilityChange,
  columnVisibilityStorageKey,
  enableColumnReorder = true,
  enableNamedLayouts = true,
  toolbarStart,
  toolbarEnd,
  toolbarClassName,
}: RegistryDataTableViewProps<TData>) {
  const isVisibilityControlled =
    columnVisibilityProp !== undefined || onColumnVisibilityChange !== undefined;
  const persistKey =
    enableColumnVisibility && !isVisibilityControlled && columnVisibilityStorageKey
      ? columnVisibilityStorageKey
      : null;
  const orderPersistKey =
    enableColumnVisibility && enableColumnReorder && persistKey ? `${persistKey}.order` : null;

  const [storedVisibility, setStoredVisibility] = useLocalStorageJsonState<VisibilityState>(
    persistKey,
    {},
    isVisibilityState,
  );
  const [storedOrder, setStoredOrder] = useLocalStorageJsonState<ColumnOrderState>(
    orderPersistKey,
    [],
    isColumnOrderState,
  );
  const [internalVisibility, setInternalVisibility] = useState<VisibilityState>({});
  const [internalOrder, setInternalOrder] = useState<ColumnOrderState>([]);
  const [internalSorting, setInternalSorting] = useState<SortingState>([]);

  const columnVisibility =
    columnVisibilityProp ?? (persistKey ? storedVisibility : internalVisibility);
  const columnOrder = orderPersistKey ? storedOrder : internalOrder;

  const handleVisibilityChange = useCallback<OnChangeFn<VisibilityState>>(
    (updater) => {
      if (onColumnVisibilityChange) {
        onColumnVisibilityChange(updater);
        return;
      }
      if (persistKey) {
        setStoredVisibility((prev) => (typeof updater === "function" ? updater(prev) : updater));
        return;
      }
      setInternalVisibility((prev) => (typeof updater === "function" ? updater(prev) : updater));
    },
    [onColumnVisibilityChange, persistKey, setStoredVisibility],
  );

  const handleColumnOrderChange = useCallback<OnChangeFn<ColumnOrderState>>(
    (updater) => {
      if (orderPersistKey) {
        setStoredOrder((prev) => (typeof updater === "function" ? updater(prev) : updater));
        return;
      }
      setInternalOrder((prev) => (typeof updater === "function" ? updater(prev) : updater));
    },
    [orderPersistKey, setStoredOrder],
  );

  const isSortingControlled = sortingProp !== undefined || onSortingChange !== undefined;
  const sorting = sortingProp ?? (!manualSorting ? internalSorting : undefined);
  const handleSortingChange = useCallback<OnChangeFn<SortingState>>(
    (updater) => {
      if (onSortingChange) {
        onSortingChange(updater);
        return;
      }
      if (!manualSorting) {
        setInternalSorting((prev) => (typeof updater === "function" ? updater(prev) : updater));
      }
    },
    [manualSorting, onSortingChange],
  );

  const table = useReactTable({
    data,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: manualSorting ? undefined : getSortedRowModel(),
    getRowId,
    enableRowSelection,
    enableMultiRowSelection,
    onRowSelectionChange,
    onSortingChange:
      isSortingControlled || !manualSorting ? handleSortingChange : undefined,
    onColumnVisibilityChange: enableColumnVisibility ? handleVisibilityChange : undefined,
    onColumnOrderChange:
      enableColumnVisibility && enableColumnReorder ? handleColumnOrderChange : undefined,
    manualSorting,
    state: {
      ...(rowSelection !== undefined ? { rowSelection } : {}),
      ...(sorting !== undefined ? { sorting } : {}),
      ...(enableColumnVisibility ? { columnVisibility } : {}),
      ...(enableColumnVisibility && enableColumnReorder ? { columnOrder } : {}),
    },
  });

  const visibleColumnIds = table.getVisibleLeafColumns().map((column) => column.id);
  const resolvedToolbarEnd =
    typeof toolbarEnd === "function"
      ? toolbarEnd({ visibleColumnIds, columnOrder })
      : toolbarEnd;
  const showToolbar = Boolean(toolbarStart || resolvedToolbarEnd || enableColumnVisibility);

  return (
    <div>
      {showToolbar ? (
        <div className={cn("flex flex-wrap items-center justify-between gap-2 border-b border-border px-3 py-2", toolbarClassName)}>
          <div className="flex flex-wrap items-center gap-2">{toolbarStart}</div>
          <div className="flex flex-wrap items-center gap-2">
            {resolvedToolbarEnd}
            {enableColumnVisibility && persistKey && enableNamedLayouts ? (
              <ModuleListLayoutMenu table={table} storageKey={layoutsStorageKey(persistKey)} />
            ) : null}
            {enableColumnVisibility ? (
              <DataTableViewOptions table={table} enableReorder={enableColumnReorder} />
            ) : null}
          </div>
        </div>
      ) : null}
      <RegistryTableScroll className={scrollClassName}>
        <DataTable
          table={table}
          isLoading={isLoading}
          isEmpty={isEmpty}
          emptyMessage={emptyMessage}
          emptyContent={emptyContent}
          loadingRowCount={loadingRowCount}
          getRowClassName={getRowClassName}
          onRowClick={onRowClick}
        />
      </RegistryTableScroll>
    </div>
  );
}
