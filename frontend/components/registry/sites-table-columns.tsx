"use client";

import type { ColumnDef } from "@tanstack/react-table";

import {
  createLinkColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type { SiteListRow } from "@/modules/sites/types";

export const sitesTableColumns: ColumnDef<SiteListRow>[] = [
  createLinkColumn("site_code", "Code", {
    href: (row) => `/sites/${row.id}`,
    label: (row) => row.site_code,
    className: "font-mono text-xs text-primary underline-offset-4 hover:underline",
    enableSorting: true,
  }),
  createLinkColumn("name", "Name", {
    href: (row) => `/sites/${row.id}`,
    label: (row) => row.name,
    className: "text-foreground underline-offset-4 hover:text-primary hover:underline",
    enableSorting: true,
  }),
  createTextColumn("type", "Type", (row) => row.type ?? "—", {
    className: "text-muted-foreground",
    enableSorting: true,
  }),
  createTextColumn("status", "Status", (row) => row.status, {
    className: "text-muted-foreground",
    enableSorting: true,
  }),
  createTextColumn(
    "coordinates",
    "Lat / Lng",
    (row) => `${row.latitude ?? "—"} / ${row.longitude ?? "—"}`,
    { className: "text-muted-foreground" },
  ),
];
