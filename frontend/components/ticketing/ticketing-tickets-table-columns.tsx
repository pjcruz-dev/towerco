"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { TicketingPriorityBadge, TicketingStatusBadge } from "@/components/ticketing/ticketing-badges";
import {
  createDateColumn,
  createLinkColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import { TICKETING_TOUR_SAMPLE_TICKET_ID } from "@/lib/help/ticketing-tour-fixtures";
import { formatTicketingCustomValue } from "@/components/ticketing/ticketing-custom-fields";
import type { TicketingTicketListRow } from "@/modules/ticketing/types";

export function createTicketingTicketsTableColumns(options?: {
  /** Preserve live-tour query when opening a ticket mid-tour. */
  tourQuery?: string;
  customFields?: Array<{ name: string; label: string }>;
  hiddenColumns?: string[];
}): ColumnDef<TicketingTicketListRow>[] {
  const suffix = options?.tourQuery ? `?${options.tourQuery}` : "";

  const hidden = new Set(options?.hiddenColumns ?? []);
  const columns: ColumnDef<TicketingTicketListRow>[] = [
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
    ...(options?.customFields ?? []).map((field) =>
      createTextColumn<TicketingTicketListRow>(
        field.name,
        field.label,
        (row) => (
          <span className="text-muted-foreground">{formatTicketingCustomValue(row.custom_values?.[field.name])}</span>
        ),
      ),
    ),
  ];

  return columns.filter((column) => !hidden.has(String(column.id)));
}

export const ticketingTicketsTableColumns = createTicketingTicketsTableColumns();
