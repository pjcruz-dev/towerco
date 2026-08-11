"use client";

import Link from "next/link";
import type { ColumnDef, Row } from "@tanstack/react-table";
import type { ReactNode } from "react";

import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";

export function formatTableDate(
  iso: string | null | undefined,
  options: Intl.DateTimeFormatOptions = { dateStyle: "short", timeStyle: "short" },
): string {
  if (!iso) return "—";
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleString(undefined, options);
}

export function createTextColumn<TData>(
  id: string,
  header: string,
  accessor: (row: TData) => ReactNode,
  options?: {
    className?: string;
    enableSorting?: boolean;
    /** Sort value when the cell renders a React node. */
    sortValue?: (row: TData) => string | number;
  },
): ColumnDef<TData, unknown> {
  return {
    id,
    accessorFn: (row) => {
      if (options?.sortValue) {
        return options.sortValue(row);
      }
      const value = accessor(row);
      return typeof value === "string" || typeof value === "number" ? value : "";
    },
    header: options?.enableSorting
      ? ({ column }) => <DataTableColumnHeader column={column} title={header} />
      : header,
    cell: ({ row }) => accessor(row.original),
    enableSorting: options?.enableSorting ?? false,
    meta: options?.className ? { className: options.className } : undefined,
  };
}

export function createLinkColumn<TData>(
  id: string,
  header: string,
  options: {
    href: (row: TData) => string;
    label: (row: TData) => ReactNode;
    className?: string;
    enableSorting?: boolean;
  },
): ColumnDef<TData, unknown> {
  return {
    id,
    accessorFn: (row) => {
      const label = options.label(row);
      return typeof label === "string" || typeof label === "number" ? label : "";
    },
    header: options.enableSorting
      ? ({ column }) => <DataTableColumnHeader column={column} title={header} />
      : header,
    cell: ({ row }) => (
      <Link href={options.href(row.original)} className={options.className ?? "text-primary hover:underline"}>
        {options.label(row.original)}
      </Link>
    ),
    enableSorting: options.enableSorting ?? false,
  };
}

export function createDateColumn<TData>(
  id: string,
  header: string,
  accessor: (row: TData) => string | null | undefined,
  options?: { className?: string; enableSorting?: boolean; dateOnly?: boolean },
): ColumnDef<TData, unknown> {
  const formatOptions: Intl.DateTimeFormatOptions = options?.dateOnly
    ? { dateStyle: "short" }
    : { dateStyle: "short", timeStyle: "short" };

  return {
    id,
    accessorFn: (row) => accessor(row) ?? "",
    header: options?.enableSorting
      ? ({ column }) => <DataTableColumnHeader column={column} title={header} />
      : header,
    cell: ({ row }) => (
      <span className={options?.className ?? "text-muted-foreground"}>
        {formatTableDate(accessor(row.original), formatOptions)}
      </span>
    ),
    enableSorting: options?.enableSorting ?? false,
  };
}

export function createActionsColumn<TData>(
  header: string,
  cell: (row: Row<TData>) => ReactNode,
): ColumnDef<TData, unknown> {
  return {
    id: "actions",
    enableHiding: false,
    enableSorting: false,
    header: () => <span className="block w-full text-right">{header}</span>,
    cell: ({ row }) => <div className="text-right">{cell(row)}</div>,
    meta: { className: "text-right" },
  };
}
