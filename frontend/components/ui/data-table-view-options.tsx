"use client";

import { Columns3 } from "lucide-react";
import type { Table } from "@tanstack/react-table";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

type DataTableViewOptionsProps<TData> = {
  table: Table<TData>;
};

/**
 * Column visibility toggle for TanStack tables.
 */
export function DataTableViewOptions<TData>({ table }: DataTableViewOptionsProps<TData>) {
  const hideable = table.getAllColumns().filter((column) => column.getCanHide());

  if (hideable.length === 0) {
    return null;
  }

  return (
    <Popover>
      <PopoverTrigger
        render={
          <Button type="button" size="sm" variant="outline" className="gap-1.5">
            <Columns3 className="size-3.5" aria-hidden />
            Columns
          </Button>
        }
      />
      <PopoverContent className="w-52 p-2" align="end">
        <p className="mb-2 px-1 text-xs font-medium text-muted-foreground">Toggle columns</p>
        <ul className="space-y-1">
          {hideable.map((column) => {
            const label =
              typeof column.columnDef.header === "string"
                ? column.columnDef.header
                : column.id.replace(/_/g, " ");

            return (
              <li key={column.id}>
                <label className="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                  <Checkbox
                    className="size-4"
                    checked={column.getIsVisible()}
                    onCheckedChange={(v) => column.toggleVisibility(v === true)}
                  />
                  <span className="capitalize">{label}</span>
                </label>
              </li>
            );
          })}
        </ul>
      </PopoverContent>
    </Popover>
  );
}
