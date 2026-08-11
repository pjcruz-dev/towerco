"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { createTextColumn } from "@/components/ui/data-table-column-helpers";
import type { FiberRouteListRow } from "@/modules/fiber-one/types";

export const fiberRoutesTableColumns: ColumnDef<FiberRouteListRow>[] = [
  createTextColumn("name", "Route", (row) => row.name, {
    className: "font-medium text-foreground",
    enableSorting: true,
  }),
  createTextColumn(
    "from",
    "From",
    (row) => (row.from_site ? `${row.from_site.site_code} · ${row.from_site.name}` : "—"),
    { className: "text-muted-foreground" },
  ),
  createTextColumn(
    "to",
    "To",
    (row) => (row.to_site ? `${row.to_site.site_code} · ${row.to_site.name}` : "—"),
    { className: "text-muted-foreground" },
  ),
  createTextColumn("length_km", "Length (km)", (row) => row.length_km ?? "—", {
    className: "text-muted-foreground",
    enableSorting: true,
  }),
  createTextColumn("status", "Status", (row) => row.status, {
    className: "text-muted-foreground",
    enableSorting: true,
  }),
];
