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
import { TablePageFooter } from "@/components/ui/table-page-footer";
import { cn } from "@/lib/utils";

function columnPin(meta: unknown): { left: number | null; edge: boolean; pinId?: string } | null {
  if (!meta || typeof meta !== "object") return null;
  const record = meta as { stickyLeft?: unknown; pinEdge?: unknown; pinId?: unknown };
  const pinId = typeof record.pinId === "string" ? record.pinId : undefined;
  const left = typeof record.stickyLeft === "number" ? record.stickyLeft : null;
  if (left == null && pinId == null) return null;
  return { left, edge: record.pinEdge === true, pinId };
}

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
  footer?: ReactNode;
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
  footer,
}: DataTableProps<TData>) {
  const columnCount = table.getVisibleLeafColumns().length;

  return (
    <>
    <Table className={cn(REGISTRY_TABLE_CLASS, tableClassName)}>
      <TableHeader className={REGISTRY_TABLE_HEADER_CLASS}>
        {table.getHeaderGroups().map((headerGroup) => (
          <TableRow key={headerGroup.id} className="hover:bg-transparent">
            {headerGroup.headers.map((header) => {
              const pin = columnPin(header.column.columnDef.meta);
              return (
              <TableHead
                key={header.id}
                data-pin-id={pin?.pinId}
                style={pin?.left != null ? { position: "sticky", left: pin.left, zIndex: 4 } : undefined}
                className={cn(
                  REGISTRY_TABLE_HEAD_CELL_CLASS,
                  header.column.columnDef.meta?.className as string | undefined,
                  pin?.left != null && "bg-card",
                  pin?.edge && "shadow-[4px_0_6px_-4px_rgba(0,0,0,0.35)]",
                )}
              >
                {header.isPlaceholder
                  ? null
                  : flexRender(header.column.columnDef.header, header.getContext())}
              </TableHead>
              );
            })}
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
              {row.getVisibleCells().map((cell) => {
                const pin = columnPin(cell.column.columnDef.meta);
                return (
                <TableCell
                  key={cell.id}
                  data-pin-id={pin?.pinId}
                  style={pin?.left != null ? { position: "sticky", left: pin.left, zIndex: 2 } : undefined}
                  className={cn(
                    cell.column.columnDef.meta?.className as string | undefined,
                    pin?.left != null && "bg-card group-hover:bg-muted group-data-[state=selected]:bg-muted",
                    pin?.edge && "shadow-[4px_0_6px_-4px_rgba(0,0,0,0.35)]",
                  )}
                  onClick={(event) => {
                    // Keep checkbox / button clicks from triggering row click
                    if (onRowClick && (event.target as HTMLElement).closest("input, button, a, [role='menuitem']")) {
                      event.stopPropagation();
                    }
                  }}
                >
                  {flexRender(cell.column.columnDef.cell, cell.getContext())}
                </TableCell>
                );
              })}
            </TableRow>
          ))
        )}
      </TableBody>
    </Table>
    {footer ? <TablePageFooter>{footer}</TablePageFooter> : null}
    </>
  );
}
