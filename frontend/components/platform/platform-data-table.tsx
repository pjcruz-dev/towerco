"use client";

import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

type Column<T> = {
  id: string;
  header: ReactNode;
  cell: (row: T) => ReactNode;
  className?: string;
  headerClassName?: string;
};

type Props<T> = {
  columns: Column<T>[];
  rows: T[];
  rowKey: (row: T) => string;
  emptyMessage?: string;
  className?: string;
  stickyHeader?: boolean;
};

export function PlatformDataTable<T>({
  columns,
  rows,
  rowKey,
  emptyMessage = "No rows to display.",
  className,
  stickyHeader = true,
}: Props<T>) {
  return (
    <div className={cn("overflow-x-auto rounded-xl border border-border bg-card shadow-sm", className)}>
      <table className="w-full min-w-[640px] border-collapse text-sm">
        <thead
          className={cn(
            "border-b border-border bg-muted/40 text-left text-xs font-medium text-muted-foreground",
            stickyHeader && "sticky top-0 z-10",
          )}
        >
          <tr>
            {columns.map((column) => (
              <th key={column.id} className={cn("px-4 py-2.5 font-medium", column.headerClassName)}>
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {rows.length === 0 ? (
            <tr>
              <td colSpan={columns.length} className="px-4 py-10 text-center text-sm text-muted-foreground">
                {emptyMessage}
              </td>
            </tr>
          ) : (
            rows.map((row) => (
              <tr key={rowKey(row)} className="hover:bg-muted/20">
                {columns.map((column) => (
                  <td key={column.id} className={cn("px-4 py-2.5 align-middle", column.className)}>
                    {column.cell(row)}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}
