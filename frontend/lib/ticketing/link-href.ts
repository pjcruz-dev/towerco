import type { TicketingLinkRow } from "@/modules/ticketing/types";

export function ticketingLinkHref(link: Pick<TicketingLinkRow, "link_module" | "link_type" | "link_id">): string | null {
  if (link.link_module === "e_approval" && link.link_type === "submission") {
    return `/e-approval/submissions/${link.link_id}`;
  }

  if (link.link_module === "ticketing" && link.link_type === "ticket") {
    return `/ticketing/tickets/${link.link_id}`;
  }

  return null;
}
