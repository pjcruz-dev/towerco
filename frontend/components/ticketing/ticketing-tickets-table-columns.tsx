"use client";

import Link from "next/link";
import type { ColumnDef } from "@tanstack/react-table";

import { TicketingPriorityBadge, TicketingStatusBadge } from "@/components/ticketing/ticketing-badges";
import {
  createDateColumn,
  createLinkColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type { TicketingTicketListRow } from "@/modules/ticketing/types";

export const ticketingTicketsTableColumns: ColumnDef<TicketingTicketListRow>[] = [
  createLinkColumn("ticket_number", "Ticket", {
    href: (row) => `/ticketing/tickets/${row.id}`,
    label: (row) => row.ticket_number,
    className: "font-mono text-xs text-primary hover:underline",
    enableSorting: true,
  }),
  createTextColumn(
    "title",
    "Title",
    (row) => <span className="max-w-xs truncate font-medium text-foreground">{row.title}</span>,
    { enableSorting: true },
  ),
  createTextColumn("status", "Status", (row) => <TicketingStatusBadge status={row.status} />, {
    enableSorting: true,
  }),
  createTextColumn(
    "priority",
    "Priority",
    (row) => <TicketingPriorityBadge priority={row.priority} />,
    { enableSorting: true },
  ),
  createTextColumn("requester", "Requester", (row) => row.requester?.name ?? "—", {
    className: "hidden text-muted-foreground md:table-cell",
  }),
  createTextColumn("assignee", "Assignee", (row) => row.assignee?.name ?? "Unassigned", {
    className: "hidden text-muted-foreground lg:table-cell",
  }),
  createDateColumn("updated_at", "Updated", (row) => row.updated_at, {
    className: "text-xs text-muted-foreground",
    enableSorting: true,
  }),
];
