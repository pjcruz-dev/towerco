"use client";

import type { ColumnDef, Row, Table } from "@tanstack/react-table";

import { Checkbox } from "@/components/ui/checkbox";

/**
 * Checkbox column for TanStack row selection (page-level select-all).
 * Parent must pass `rowSelection` / `onRowSelectionChange` into RegistryDataTableView.
 */
export function createRowSelectionColumn<TData>(): ColumnDef<TData, unknown> {
  return {
    id: "select",
    enableSorting: false,
    enableHiding: false,
    header: ({ table }: { table: Table<TData> }) => (
      <Checkbox
        className="size-4"
        aria-label="Select all rows on this page"
        checked={
          table.getIsAllPageRowsSelected()
            ? true
            : table.getIsSomePageRowsSelected()
              ? "indeterminate"
              : false
        }
        onCheckedChange={(v) => table.toggleAllPageRowsSelected(v === true)}
        disabled={!table.getRowModel().rows.some((row) => row.getCanSelect())}
      />
    ),
    cell: ({ row }: { row: Row<TData> }) => (
      <Checkbox
        className="size-4"
        aria-label="Select row"
        checked={row.getIsSelected()}
        disabled={!row.getCanSelect()}
        onCheckedChange={(v) => row.toggleSelected(v === true)}
      />
    ),
    meta: { className: "w-10" },
  };
}
