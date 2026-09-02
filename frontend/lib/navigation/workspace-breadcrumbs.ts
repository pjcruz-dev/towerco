export type WorkspaceBreadcrumb = {
  label: string;
  href?: string;
};

const MODULE_ROOTS: Record<string, { label: string; href: string }> = {
  "e-approval": { label: "E-Approval", href: "/e-approval" },
  "dynamic-entities": { label: "Dynamic Entities", href: "/dynamic-entities" },
  ticketing: { label: "Ticketing", href: "/ticketing" },
};

const SEGMENT_LABELS: Record<string, string> = {
  dashboard: "Dashboard",
  notifications: "Notifications",
  users: "Users",
  roles: "Roles & permissions",
  billing: "Billing",
  settings: "Settings",
  account: "Account",
  security: "My security",
  admin: "Administration",
  kpi: "KPI & SLA",
  forms: "Forms",
  submissions: "Submissions",
  templates: "Templates",
  audit: "Audit log",
  "approval-policies": "Approval policies",
  profile: "My profile",
  reports: "Reports",
  request: "New request",
  "master-data": "Master data",
  new: "New",
  create: "New form",
  tickets: "Tickets",
  fields: "Manage Fields",
  "field-groups": "Field Groups",
  "executive-dashboard": "Executive Dashboard",
  "ticketing-board": "Ticketing Board",
};

const NEW_SEGMENT_LABELS: Record<string, string> = {
  "e-approval/submissions/new": "New request",
  "ticketing/tickets/new": "New ticket",
};

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

function formatSegment(segment: string): string {
  return segment
    .split("-")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

function labelForSegment(segment: string, pathPrefix: string, isLast: boolean): string {
  if (segment === "new" && isLast) {
    return NEW_SEGMENT_LABELS[pathPrefix] ?? "New";
  }

  if (UUID_PATTERN.test(segment)) {
    return isLast ? "Detail" : segment;
  }

  return SEGMENT_LABELS[segment] ?? formatSegment(segment);
}

function normalizePathname(pathname: string): string {
  const pathOnly = pathname.split("?")[0] ?? pathname;
  return pathOnly.replace(/\/$/, "") || "/";
}

function pushPathSegments(parts: string[], startIndex: number, crumbs: WorkspaceBreadcrumb[]): WorkspaceBreadcrumb[] {
  for (let index = startIndex; index < parts.length; index += 1) {
    const segment = parts[index]!;
    const isLast = index === parts.length - 1;
    const pathPrefix = parts.slice(0, index + 1).join("/");
    const label = labelForSegment(segment, pathPrefix, isLast);
    const href = isLast ? undefined : `/${pathPrefix}`;

    crumbs.push({ label, href });
  }

  return crumbs;
}

/**
 * Resolves sidebar-aligned breadcrumbs: Module / Feature / Current.
 * Returns an empty array on shallow top-level pages (e.g. /dashboard).
 */
export function resolveWorkspaceBreadcrumbs(pathname: string): WorkspaceBreadcrumb[] {
  const normalizedPath = normalizePathname(pathname);
  const parts = normalizedPath.split("/").filter(Boolean);

  if (parts.length === 0) {
    return [];
  }

  const root = parts[0]!;
  const moduleRoot = MODULE_ROOTS[root];

  if (moduleRoot) {
    if (parts.length === 1) {
      return [];
    }

    const crumbs: WorkspaceBreadcrumb[] = [{ label: moduleRoot.label, href: moduleRoot.href }];
    pushPathSegments(parts, 1, crumbs);
    return crumbs;
  }

  if (root === "users") {
    const crumbs: WorkspaceBreadcrumb[] = [{ label: "Team & Access", href: "/users" }];

    if (parts.length === 1) {
      crumbs.push({ label: "Users" });
      return crumbs;
    }

    if (parts[1] === "roles") {
      crumbs.push({ label: "Roles & permissions" });
      return crumbs;
    }

    if (parts[1] === "org") {
      crumbs.push({ label: "Organization" });
      return crumbs;
    }

    pushPathSegments(parts, 1, crumbs);
    return crumbs;
  }

  if (root === "settings") {
    const crumbs: WorkspaceBreadcrumb[] = [{ label: "Settings", href: "/settings" }];
    pushPathSegments(parts, 1, crumbs);
    return crumbs;
  }

  if (root === "account") {
    const crumbs: WorkspaceBreadcrumb[] = [{ label: "Account", href: "/account/security" }];
    pushPathSegments(parts, 1, crumbs);
    return crumbs;
  }

  if (root === "admin" && parts[1] === "settings") {
    const crumbs: WorkspaceBreadcrumb[] = [{ label: "Settings", href: "/settings" }];
    pushPathSegments(parts, 2, crumbs);
    return crumbs;
  }

  const shallowRoots = new Set(["dashboard", "notifications", "billing"]);
  if (parts.length === 1 && shallowRoots.has(root)) {
    return [];
  }

  const crumbs: WorkspaceBreadcrumb[] = [];
  pushPathSegments(parts, 0, crumbs);
  return crumbs.length > 1 ? crumbs : [];
}
