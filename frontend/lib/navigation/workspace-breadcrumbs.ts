import {
  FINANCE_ONE_HOME,
  FINANCE_ONE_PROCUREMENT_SEGMENTS,
} from "@/lib/navigation/finance-one-routes";

export type WorkspaceBreadcrumb = {
  label: string;
  href?: string;
};

const MODULE_ROOTS: Record<string, { label: string; href: string }> = {
  "project-one": { label: "Project-One", href: "/project-one" },
  "tower-one": { label: "TOWER-ONE", href: "/tower-one" },
  "fiber-one": { label: "FIBER-ONE", href: "/fiber-one" },
  "asset-one": { label: "ASSET-ONE", href: "/asset-one" },
  "e-approval": { label: "E-Approval", href: "/e-approval" },
  procurement: { label: "Procurement-One", href: "/procurement" },
  finance: { label: "Finance-One", href: FINANCE_ONE_HOME },
};

const SEGMENT_LABELS: Record<string, string> = {
  dashboard: "Dashboard",
  notifications: "Notifications",
  sites: "Sites",
  rollouts: "Rollouts",
  "rollout-playbook": "Playbook",
  "public-holidays": "Holidays",
  "gate-approvals": "Gate approvals",
  projects: "Projects",
  approvals: "Approvals",
  towers: "Towers",
  routes: "Fiber routes",
  assets: "Assets",
  gis: "GIS",
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
  procurement: "Procurement-One",
  finance: "Finance-One",
  budget: "Budget & encumbrance",
  "ap-invoices": "AP invoices",
  payments: "Payment tracking",
  contracts: "Vendor contracts",
  reports: "Reports & exports",
  request: "New request",
  "master-data": "Master data",
  new: "New",
  create: "New form",
  batch: "Batch",
};

const NEW_SEGMENT_LABELS: Record<string, string> = {
  "project-one/approvals/new": "New approval",
  "e-approval/submissions/new": "New request",
  "project-one/rollouts/batch/new": "New batch",
  "project-one/projects/new": "New project",
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
 * Returns an empty array on shallow top-level pages (e.g. /dashboard, /project-one).
 */
export function resolveWorkspaceBreadcrumbs(pathname: string): WorkspaceBreadcrumb[] {
  const normalizedPath = normalizePathname(pathname);
  const parts = normalizedPath.split("/").filter(Boolean);

  if (parts.length === 0) {
    return [];
  }

  const root = parts[0]!;
  const moduleRoot = MODULE_ROOTS[root];

  if (
    root === "procurement" &&
    parts[1] &&
    FINANCE_ONE_PROCUREMENT_SEGMENTS.has(parts[1])
  ) {
    if (parts.length === 2) {
      return [];
    }

    const crumbs: WorkspaceBreadcrumb[] = [{ label: "Finance-One", href: FINANCE_ONE_HOME }];
    for (let index = 1; index < parts.length; index += 1) {
      const segment = parts[index]!;
      const isLast = index === parts.length - 1;
      const pathPrefix = parts.slice(0, index + 1).join("/");
      const label = labelForSegment(segment, pathPrefix, isLast);
      const financePath = `/finance/${parts.slice(1, index + 1).join("/")}`;

      crumbs.push({ label, href: isLast ? undefined : financePath });
    }

    return crumbs;
  }

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

  const shallowRoots = new Set(["dashboard", "notifications", "sites", "gis", "billing"]);
  if (parts.length === 1 && shallowRoots.has(root)) {
    return [];
  }

  const crumbs: WorkspaceBreadcrumb[] = [];
  pushPathSegments(parts, 0, crumbs);
  return crumbs.length > 1 ? crumbs : [];
}
