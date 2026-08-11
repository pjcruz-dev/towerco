"use client";

import type { ColumnDef } from "@tanstack/react-table";

import {
  createLinkColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type { ProjectListRow } from "@/modules/project-one/types";

export const projectOneProjectsTableColumns: ColumnDef<ProjectListRow>[] = [
  createLinkColumn("name", "Project", {
    href: (row) => `/project-one/projects/${row.id}`,
    label: (row) => row.name,
    className: "font-medium text-primary underline-offset-4 hover:underline",
    enableSorting: true,
  }),
  createTextColumn(
    "site",
    "Site",
    (row) => (row.site ? `${row.site.site_code} · ${row.site.name}` : "—"),
    { className: "text-muted-foreground" },
  ),
  createTextColumn("manager", "Manager", (row) => row.project_manager?.name ?? "—", {
    className: "text-muted-foreground",
  }),
  createTextColumn("status", "Status", (row) => row.status, {
    className: "text-muted-foreground",
    enableSorting: true,
  }),
  createTextColumn(
    "dates",
    "Dates",
    (row) => `${row.start_date ?? "—"} → ${row.end_date ?? "—"}`,
    { className: "text-muted-foreground" },
  ),
];
