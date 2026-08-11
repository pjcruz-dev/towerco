"use client";

import type { ColumnDef } from "@tanstack/react-table";

import {
  createDateColumn,
  createLinkColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type { AssetListRow } from "@/modules/asset-one/types";

export const assetsTableColumns: ColumnDef<AssetListRow>[] = [
  createLinkColumn("asset_code", "Code", {
    href: (row) => `/asset-one/assets/${row.id}`,
    label: (row) => row.asset_code,
    className: "font-mono text-xs text-primary underline-offset-4 hover:underline",
    enableSorting: true,
  }),
  createLinkColumn("name", "Name", {
    href: (row) => `/asset-one/assets/${row.id}`,
    label: (row) => row.name,
    className: "text-foreground underline-offset-4 hover:text-primary hover:underline",
    enableSorting: true,
  }),
  createTextColumn("category", "Category", (row) => row.category, {
    className: "text-muted-foreground",
    enableSorting: true,
  }),
  createTextColumn("status", "Status", (row) => row.status, {
    className: "text-muted-foreground",
    enableSorting: true,
  }),
  createTextColumn(
    "location",
    "Location",
    (row) =>
      row.location_type && row.location_id
        ? `${row.location_type}:${row.location_id.slice(0, 8)}…`
        : "—",
    { className: "text-muted-foreground" },
  ),
  createDateColumn("warranty_expiry", "Warranty", (row) => row.warranty_expiry, {
    dateOnly: true,
    enableSorting: true,
  }),
];
