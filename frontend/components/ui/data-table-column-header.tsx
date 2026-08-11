"use client";

import { ArrowDown, ArrowUp, ArrowUpDown } from "lucide-react";
import type { Column } from "@tanstack/react-table";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

type DataTableColumnHeaderProps<TData, TValue> = {
  column: Column<TData, TValue>;
  title: string;
  className?: string;
};

/**
 * Sortable column header for client-side or manual server sort.
 * Enable sorting on the column def (`enableSorting: true`) and wire
 * `sorting` / `onSortingChange` on RegistryDataTableView when ready.
 */
export function DataTableColumnHeader<TData, TValue>({
  column,
  title,
  className,
}: DataTableColumnHeaderProps<TData, TValue>) {
  if (!column.getCanSort()) {
    return <span className={cn("text-xs font-medium text-muted-foreground", className)}>{title}</span>;
  }

  const sorted = column.getIsSorted();

  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      className={cn("-ml-2 h-8 gap-1 px-2 font-medium text-muted-foreground hover:text-foreground", className)}
      onClick={column.getToggleSortingHandler()}
    >
      {title}
      {sorted === "desc" ? (
        <ArrowDown className="size-3.5" aria-hidden />
      ) : sorted === "asc" ? (
        <ArrowUp className="size-3.5" aria-hidden />
      ) : (
        <ArrowUpDown className="size-3.5 opacity-50" aria-hidden />
      )}
    </Button>
  );
}
