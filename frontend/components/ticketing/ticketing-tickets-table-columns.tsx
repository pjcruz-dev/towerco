"use client";

import type { ColumnDef } from "@tanstack/react-table";

import { TicketingPriorityBadge, TicketingStatusBadge } from "@/components/ticketing/ticketing-badges";
import {
  createActionsColumn,
  createDateColumn,
  createLinkColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import { TICKETING_TOUR_SAMPLE_TICKET_ID } from "@/lib/help/ticketing-tour-fixtures";
import { formatTicketingCustomValue } from "@/components/ticketing/ticketing-custom-fields";
import type { TicketingTicketListRow } from "@/modules/ticketing/types";

/** Status options offered from the row/bulk "Change status" menus. Resolving a
 * ticket requires a resolution comment, so it stays a detail-page-only action. */
export const TICKETING_STATUS_MENU_OPTIONS: Array<{ value: string; label: string }> = [
  { value: "open", label: "Open" },
  { value: "in_progress", label: "In progress" },
  { value: "closed", label: "Closed" },
];

export function createTicketingTicketsTableColumns(options?: {
  /** Preserve live-tour query when opening a ticket mid-tour. */
  tourQuery?: string;
  customFields?: Array<{ name: string; label: string }>;
  hiddenColumns?: string[];
  /** Current signed-in user id, to hide "Assign to me" when already the assignee. */
  currentUserId?: string;
  onAssignToMe?: (row: TicketingTicketListRow) => void;
  onChangeStatus?: (row: TicketingTicketListRow, status: string) => void;
  canOpen?: boolean;
}): ColumnDef<TicketingTicketListRow>[] {
  const suffix = options?.tourQuery ? `?${options.tourQuery}` : "";
  const canOpen = options?.canOpen !== false;

  const hidden = new Set(options?.hiddenColumns ?? []);
  const columns: ColumnDef<TicketingTicketListRow>[] = [
    canOpen
      ? createLinkColumn("ticket_number", "Ticket", {
          href: (row) => `/ticketing/tickets/${row.id}${suffix}`,
          label: (row) => row.ticket_number,
          className: "font-mono text-xs text-primary hover:underline",
          enableSorting: true,
          dataHelp: (row) =>
            row.id === TICKETING_TOUR_SAMPLE_TICKET_ID ? "tk-tickets-open" : undefined,
        })
      : createTextColumn("ticket_number", "Ticket", (row) => (
          <span className="font-mono text-xs">{row.ticket_number}</span>
        ), { enableSorting: true }),
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
    createActionsColumn<TicketingTicketListRow>("Actions", (row) => {
      const ticket = row.original;
      const isAssignedToMe = Boolean(options?.currentUserId) && ticket.assignee?.id === options?.currentUserId;
      const statusMenuOptions = TICKETING_STATUS_MENU_OPTIONS.filter((item) => item.value !== ticket.status);

      return (
        <RowActionsMenu
          items={[
            {
              key: "open",
              label: "Open",
              href: `/ticketing/tickets/${ticket.id}${suffix}`,
              hidden: !canOpen,
            },
            {
              key: "assign-to-me",
              label: "Assign to me",
              hidden: !options?.onAssignToMe || isAssignedToMe,
              onSelect: () => options?.onAssignToMe?.(ticket),
            },
            {
              type: "submenu",
              key: "change-status",
              label: "Change status",
              hidden: !options?.onChangeStatus || statusMenuOptions.length === 0,
              items: statusMenuOptions.map((item) => ({
                key: item.value,
                label: item.label,
                onSelect: () => options?.onChangeStatus?.(ticket, item.value),
              })),
            },
          ]}
        />
      );
    }),
  ];

  return columns.filter((column) => !hidden.has(String(column.id)));
}

export const ticketingTicketsTableColumns = createTicketingTicketsTableColumns();
