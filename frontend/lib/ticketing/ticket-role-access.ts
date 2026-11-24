import type { RoleAccessMatrix } from "@/lib/api/modules/admin-roles-api";
import { canEntityAction, type EntityAccessAction } from "@/lib/rbac/entity-access";

/** Access-matrix slug for tickets. A missing row stays open. */
export const TICKETING_TICKET_ENTITY = "ticketing_ticket";

/** Shared by the Ticketing pages and both navigation layouts. */
export const TICKETING_VIEWING_AS_KEY = "toweros.ticketing.viewing-as";

/** Pages an admin can hide for the role they are viewing as. Overview matches only its own URL. */
export const TICKETING_NAV_TARGETS = [
  { href: "/ticketing", label: "Overview" },
  { href: "/ticketing/tickets", label: "Tickets" },
  { href: "/ticketing/tickets/new", label: "New ticket" },
  { href: "/ticketing/reports", label: "Reports" },
  { href: "/ticketing/fields", label: "Fields" },
] as const;

export const TICKETING_LIST_FIELDS = [
  { id: "ticket_no", label: "Ticket" },
  { id: "title", label: "Title" },
  { id: "status", label: "Status" },
  { id: "priority", label: "Priority" },
  { id: "category", label: "Category" },
  { id: "department", label: "Department" },
  { id: "assignee", label: "Assignee" },
] as const;

export function ticketingNavTargetKey(href: string): string {
  return `nav:${ticketingPath(href)}`;
}

export function ticketingNavTargetHidden(
  matrix: RoleAccessMatrix | null | undefined,
  href: string,
): boolean {
  const path = ticketingPath(href);
  if (!path.startsWith("/ticketing")) return false;
  const fields = matrix?.fields?.[TICKETING_TICKET_ENTITY];
  if (!fields) return false;

  for (const target of TICKETING_NAV_TARGETS) {
    if (fields[ticketingNavTargetKey(target.href)] !== "hide") continue;
    if (ticketingPathMatchesTarget(path, target.href)) return true;
  }
  return false;
}

export function ticketingHasHiddenNavTargets(matrix: RoleAccessMatrix | null | undefined): boolean {
  const fields = matrix?.fields?.[TICKETING_TICKET_ENTITY];
  if (!fields) return false;
  return TICKETING_NAV_TARGETS.some((target) => fields[ticketingNavTargetKey(target.href)] === "hide");
}

/** The page a right-click is about. Longer paths win, so a new ticket is not the ticket list. */
export function ticketingNavTargetForPath(href: string): (typeof TICKETING_NAV_TARGETS)[number] | null {
  const path = ticketingPath(href);
  const ordered = [...TICKETING_NAV_TARGETS].sort((left, right) => right.href.length - left.href.length);
  return ordered.find((target) => ticketingPathMatchesTarget(path, target.href)) ?? null;
}

function ticketingPath(href: string): string {
  return (href.split("?")[0] ?? href).split("#")[0] ?? href;
}

function ticketingPathMatchesTarget(path: string, target: string): boolean {
  if (target === "/ticketing") return path === "/ticketing" || path === "/ticketing/";
  if (target === "/ticketing/tickets") {
    return (path === target || path.startsWith(`${target}/`)) && !path.startsWith(`${target}/new`);
  }
  return path === target || path.startsWith(`${target}/`);
}

/**
 * Missing slug stays allowed. A role configured only for E-Forms must not lose Ticketing.
 * Once this slug has a row, the row decides.
 */
export function ticketingEntityAllows(
  matrix: RoleAccessMatrix | null | undefined,
  action: EntityAccessAction,
): boolean {
  const row = matrix?.entities?.[TICKETING_TICKET_ENTITY];
  if (!row) return true;
  return canEntityAction(matrix, TICKETING_TICKET_ENTITY, action);
}

type NavRule = {
  match: (path: string) => boolean;
  permissions: string[];
  mode: "any" | "all";
  action?: EntityAccessAction;
};

const NAV_RULES: NavRule[] = [
  {
    match: (path) => path.startsWith("/ticketing/settings"),
    permissions: ["ticketing:settings:manage"],
    mode: "all",
  },
  {
    match: (path) => path.startsWith("/ticketing/fields"),
    permissions: ["ticketing:fields:manage"],
    mode: "all",
  },
  {
    match: (path) => path.startsWith("/ticketing/tickets/new"),
    permissions: ["ticketing:tickets:create"],
    mode: "all",
    action: "create",
  },
  {
    match: (path) => path.startsWith("/ticketing"),
    permissions: ["ticketing:view"],
    mode: "all",
    action: "view",
  },
];

function roleHasPermissions(granted: readonly string[], required: string[], mode: "any" | "all"): boolean {
  if (required.length === 0) return true;
  return mode === "any"
    ? required.some((permission) => granted.includes(permission))
    : required.every((permission) => granted.includes(permission));
}

/** Whether a preview role may open this href. Paths outside Ticketing stay visible. */
export function ticketingPreviewAllowsPath(
  granted: readonly string[] | null | undefined,
  matrix: RoleAccessMatrix | null | undefined,
  href: string,
): boolean {
  const path = ticketingPath(href);
  if (!path.startsWith("/ticketing")) return true;
  if (ticketingNavTargetHidden(matrix, path)) return false;

  const permissions = granted ?? [];
  const rule = NAV_RULES.find((candidate) => candidate.match(path));
  if (!rule) return true;
  if (!roleHasPermissions(permissions, rule.permissions, rule.mode)) return false;
  if (rule.action && !ticketingEntityAllows(matrix, rule.action)) return false;
  return true;
}

/** What the navigation does with this page: shown, hidden by the admin, or unavailable for the role. */
export function ticketingPageListState(
  granted: readonly string[] | null | undefined,
  matrix: RoleAccessMatrix | null | undefined,
  href: string,
): "shown" | "hidden" | "no-access" {
  const fields = { ...(matrix?.fields?.[TICKETING_TICKET_ENTITY] ?? {}) };
  const key = ticketingNavTargetKey(href);
  const hidden = fields[key] === "hide";
  delete fields[key];
  const stripped: RoleAccessMatrix = {
    ...(matrix ?? {}),
    fields: {
      ...(matrix?.fields ?? {}),
      [TICKETING_TICKET_ENTITY]: fields,
    },
  };
  if (!ticketingPreviewAllowsPath(granted, stripped, href)) return "no-access";
  return hidden ? "hidden" : "shown";
}

type PreviewNavNode = {
  title: string;
  href?: string | null;
  items?: Array<{ href?: string | null }>;
};

/** Hide Ticketing links the preview role cannot open. The group stays so it can still be right-clicked. */
export function filterNavGroupsForTicketingPreview<T extends PreviewNavNode>(
  groups: Array<{ group: string; items: T[] }>,
  allowsHref: (href: string) => boolean,
): Array<{ group: string; items: T[] }> {
  return groups
    .map((group) => ({
      ...group,
      items: group.items.flatMap((item): T[] => {
        if (item.items) {
          if (item.items.length === 0) {
            return [item];
          }
          const next = item.items.filter((sub) => allowsHref(sub.href ?? ""));
          if (item.title === "Ticketing") {
            return [{ ...item, items: next }];
          }
          if (next.length === 0) {
            return item.href && allowsHref(item.href) ? [{ ...item, items: next }] : [];
          }
          return [{ ...item, items: next }];
        }
        if (item.href && !allowsHref(item.href)) return [];
        return [item];
      }),
    }))
    .filter((group) => group.items.length > 0);
}
