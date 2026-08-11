"use client";

import type { ReactNode } from "react";

import { Skeleton } from "@/components/ui/skeleton";
import { TableCell, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";

/** Operational table density — theme spec: 13px table text. */
export const REGISTRY_TABLE_CLASS = "text-[13px]";

export const REGISTRY_TABLE_HEADER_CLASS = "sticky top-0 z-10 bg-card";

export const REGISTRY_TABLE_HEAD_CELL_CLASS =
  "bg-card text-xs font-medium text-muted-foreground";

type RegistryTableScrollProps = {
  children: ReactNode;
  className?: string;
};

export function RegistryTableScroll({ children, className }: RegistryTableScrollProps) {
  return (
    <div className={cn("max-h-[min(70vh,720px)] overflow-auto", className)}>{children}</div>
  );
}

type RegistryTableLoadingRowsProps = {
  columns: number;
  rows?: number;
};

export function RegistryTableLoadingRows({ columns, rows = 6 }: RegistryTableLoadingRowsProps) {
  return (
    <>
      {Array.from({ length: rows }).map((_, rowIndex) => (
        <TableRow key={`registry-loading-${rowIndex}`} className="hover:bg-transparent">
          {Array.from({ length: columns }).map((_, colIndex) => (
            <TableCell key={`registry-loading-${rowIndex}-${colIndex}`}>
              <Skeleton
                className={cn("h-4", colIndex === 0 ? "w-24" : "w-full max-w-[140px]")}
              />
            </TableCell>
          ))}
        </TableRow>
      ))}
    </>
  );
}

type RegistryTableEmptyRowProps = {
  columns: number;
  message: string;
};

export function RegistryTableEmptyRow({ columns, message }: RegistryTableEmptyRowProps) {
  return (
    <TableRow className="hover:bg-transparent">
      <TableCell colSpan={columns} className="py-8 text-center text-muted-foreground">
        {message}
      </TableCell>
    </TableRow>
  );
}
