"use client";

import Link from "next/link";
import type { ColumnDef } from "@tanstack/react-table";

import {
  createLinkColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type { TowerListRow } from "@/modules/tower-one/types";

export const towersTableColumns: ColumnDef<TowerListRow>[] = [
  createTextColumn(
    "site",
    "Site",
    (row) =>
      row.site ? (
        <Link className="text-primary underline-offset-4 hover:underline" href={`/sites/${row.site.id}`}>
          {row.site.site_code} · {row.site.name}
        </Link>
      ) : (
        "—"
      ),
  ),
  createLinkColumn("tower_type", "Type", {
    href: (row) => `/tower-one/towers/${row.id}`,
    label: (row) => row.tower_type,
    className: "capitalize text-foreground underline-offset-4 hover:text-primary hover:underline",
    enableSorting: true,
  }),
  createTextColumn("height_m", "Height (m)", (row) => row.height_m ?? "—", {
    className: "text-muted-foreground",
    enableSorting: true,
  }),
  createTextColumn("capacity_kg", "Capacity (kg)", (row) => row.capacity_kg ?? "—", {
    className: "text-muted-foreground",
    enableSorting: true,
  }),
  createTextColumn("max_tenants", "Max tenants", (row) => row.max_tenants ?? "—", {
    className: "text-muted-foreground",
    enableSorting: true,
  }),
  createTextColumn("status", "Status", (row) => row.status, {
    className: "text-muted-foreground",
    enableSorting: true,
  }),
];
