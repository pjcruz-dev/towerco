"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { TicketingPriorityBadge, TicketingStatusBadge } from "@/components/ticketing/ticketing-badges";
import {
  createDateColumn,
  createLinkColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import { TICKETING_TOUR_SAMPLE_TICKET_ID } from "@/lib/help/ticketing-tour-fixtures";
import type { TicketingTicketListRow } from "@/modules/ticketing/types";

export function createTicketingTicketsTableColumns(options?: {
  /** Preserve live-tour query when opening a ticket mid-tour. */
  tourQuery?: string;
}): ColumnDef<TicketingTicketListRow>[] {
  const suffix = options?.tourQuery ? `?${options.tourQuery}` : "";

  return [
    createLinkColumn("ticket_number", "Ticket", {
      href: (row) => `/ticketing/tickets/${row.id}${suffix}`,
      label: (row) => row.ticket_number,
      className: "font-mono text-xs text-primary hover:underline",
      enableSorting: true,
      dataHelp: (row) =>
        row.id === TICKETING_TOUR_SAMPLE_TICKET_ID ? "tk-tickets-open" : undefined,
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
}

export const ticketingTicketsTableColumns = createTicketingTicketsTableColumns();
