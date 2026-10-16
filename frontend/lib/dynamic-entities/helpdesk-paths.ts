/** Helpdesk records stay under /ticketing. Other entities stay under /dynamic-entities. */

export function dynListPath(slug: string): string {
  if (slug === "helpdesk_ticket") return "/ticketing/tickets";
  return `/dynamic-entities/${slug}`;
}

export function dynRecordPath(slug: string, id: string): string {
  if (slug === "helpdesk_ticket") return `/ticketing/tickets/${id}`;
  return `/dynamic-entities/records/${id}`;
}

export function dynNewPath(slug: string): string {
  if (slug === "helpdesk_ticket") return "/ticketing/tickets/new";
  return `/dynamic-entities/${slug}/new`;
}

export function dynFieldsPath(slug: string): string {
  if (slug === "helpdesk_ticket") return "/ticketing/fields";
  return `/dynamic-entities/fields?entity=${encodeURIComponent(slug)}`;
}
