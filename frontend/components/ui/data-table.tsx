"use client";

import {
  flexRender,
  type Row,
  type Table as TanStackTable,
} from "@tanstack/react-table";
import type { ReactNode } from "react";

import {
  REGISTRY_TABLE_CLASS,
  REGISTRY_TABLE_HEAD_CELL_CLASS,
  REGISTRY_TABLE_HEADER_CLASS,
  RegistryTableEmptyRow,
  RegistryTableLoadingRows,
} from "@/components/registry/registry-data-table";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { cn } from "@/lib/utils";

type DataTableProps<TData> = {
  table: TanStackTable<TData>;
  isLoading?: boolean;
  isEmpty?: boolean;
  emptyMessage?: string;
  emptyContent?: ReactNode;
  loadingRowCount?: number;
  tableClassName?: string;
  getRowClassName?: (row: Row<TData>) => string | undefined;
  onRowClick?: (row: Row<TData>) => void;
};

export function DataTable<TData>({
  table,
  isLoading = false,
  isEmpty = false,
  emptyMessage = "No rows match this filter.",
  emptyContent,
  loadingRowCount = 6,
  tableClassName,
  getRowClassName,
  onRowClick,
}: DataTableProps<TData>) {
  const columnCount = table.getVisibleLeafColumns().length;

  return (
    <Table className={cn(REGISTRY_TABLE_CLASS, tableClassName)}>
      <TableHeader className={REGISTRY_TABLE_HEADER_CLASS}>
        {table.getHeaderGroups().map((headerGroup) => (
          <TableRow key={headerGroup.id} className="hover:bg-transparent">
            {headerGroup.headers.map((header) => (
              <TableHead
                key={header.id}
                className={cn(
                  REGISTRY_TABLE_HEAD_CELL_CLASS,
                  header.column.columnDef.meta?.className as string | undefined,
                )}
              >
                {header.isPlaceholder
                  ? null
                  : flexRender(header.column.columnDef.header, header.getContext())}
              </TableHead>
            ))}
          </TableRow>
        ))}
      </TableHeader>
      <TableBody>
        {isLoading && table.getRowModel().rows.length === 0 ? (
          <RegistryTableLoadingRows columns={columnCount} rows={loadingRowCount} />
        ) : isEmpty ? (
          emptyContent ? (
            <TableRow className="hover:bg-transparent">
              <TableCell colSpan={columnCount} className="p-0">
                {emptyContent}
              </TableCell>
            </TableRow>
          ) : (
            <RegistryTableEmptyRow columns={columnCount} message={emptyMessage} />
          )
        ) : (
          table.getRowModel().rows.map((row) => (
            <TableRow
              key={row.id}
              className={cn("group", onRowClick ? "cursor-pointer" : undefined, getRowClassName?.(row))}
              onClick={onRowClick ? () => onRowClick(row) : undefined}
              data-state={row.getIsSelected() ? "selected" : undefined}
            >
              {row.getVisibleCells().map((cell) => (
                <TableCell
                  key={cell.id}
                  className={cell.column.columnDef.meta?.className as string | undefined}
                  onClick={(event) => {
                    // Keep checkbox / button clicks from triggering row click
                    if (onRowClick && (event.target as HTMLElement).closest("input, button, a, [role='menuitem']")) {
                      event.stopPropagation();
                    }
                  }}
                >
                  {flexRender(cell.column.columnDef.cell, cell.getContext())}
                </TableCell>
              ))}
            </TableRow>
          ))
        )}
      </TableBody>
    </Table>
  );
}
