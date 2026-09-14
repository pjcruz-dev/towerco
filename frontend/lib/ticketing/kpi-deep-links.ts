/**
 * Deep links from Ticketing KPI keys → filtered ticket queue.
 * Kept client-side as fallback when API omits href.
 */
export function ticketingKpiHref(key: string): string | null {
  switch (key) {
    case "open":
      return "/ticketing/tickets?status=open,in_progress";
    case "assigned_me":
      return "/ticketing/tickets?assigned_me=1";
    case "urgent":
      return "/ticketing/tickets?priority=urgent&status=open,in_progress";
    case "sla_at_risk":
      return "/ticketing/tickets?sla_status=at_risk,breached&status=open,in_progress";
    case "resolved_week":
      return "/ticketing/tickets?status=resolved";
    default:
      return null;
  }
}

export function withTicketingKpiHrefs<
  T extends { key: string; href?: string | null },
>(kpis: T[]): Array<T & { href?: string | null }> {
  return kpis.map((kpi) => ({
    ...kpi,
    href: kpi.href?.trim() || ticketingKpiHref(kpi.key) || null,
  }));
}

/** Apply ticket-list query params onto workspace filter prefs. */
export function ticketingPrefsFromSearchParams(params: URLSearchParams): {
  status?: string;
  priority?: string;
  category?: string;
  department?: string;
  mineOnly?: boolean;
  assignedMe?: boolean;
  slaStatus?: string;
} {
  const patch: {
    status?: string;
    priority?: string;
    category?: string;
    department?: string;
    mineOnly?: boolean;
    assignedMe?: boolean;
    slaStatus?: string;
  } = {};

  const status = params.get("status");
  if (status != null && status !== "") patch.status = status;

  const priority = params.get("priority");
  if (priority != null && priority !== "") patch.priority = priority;

  const category = params.get("category");
  if (category != null && category !== "") patch.category = category;

  const department = params.get("department");
  if (department != null && department !== "") patch.department = department;

  const slaStatus = params.get("sla_status");
  if (slaStatus != null && slaStatus !== "") patch.slaStatus = slaStatus;

  if (params.get("assigned_me") === "1") {
    patch.assignedMe = true;
    patch.mineOnly = false;
  }
  if (params.get("mine") === "1") {
    patch.mineOnly = true;
    patch.assignedMe = false;
  }

  return patch;
}
