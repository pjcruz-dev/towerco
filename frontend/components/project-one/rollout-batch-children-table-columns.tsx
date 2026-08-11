"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { AcronymLabel } from "@/components/help/acronym-label";
import {
  createLinkColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";

export type RolloutBatchChildRow = {
  id: string;
  rollout_ref: string;
  search_ring_name: string | null;
  status: string;
  tco_site_id: string | null;
};

export const rolloutBatchChildrenTableColumns: ColumnDef<RolloutBatchChildRow>[] = [
  createLinkColumn("rollout_ref", "Child rollout", {
    href: (row) => `/project-one/rollouts/${row.id}`,
    label: (row) => row.rollout_ref,
    className: "font-medium text-primary underline-offset-4 hover:underline",
  }),
  createTextColumn("search_ring_name", "Search ring", (row) => row.search_ring_name ?? "—"),
  createTextColumn("status", "Status", (row) => (
    <span className="capitalize">{row.status}</span>
  )),
  {
    id: "tco_site_id",
    accessorFn: (row) => row.tco_site_id ?? "",
    header: () => <AcronymLabel term="TCO ID">TCO Site ID</AcronymLabel>,
    cell: ({ row }) => (
      <span className="font-mono text-xs">{row.original.tco_site_id ?? "—"}</span>
    ),
    enableSorting: false,
  },
];
