import type { RoleAccessMatrix } from "@/lib/api/modules/admin-roles-api";
import { canEntityAction, type EntityAccessAction } from "@/lib/rbac/entity-access";

/** Same access-matrix slug for the submissions list, the record, and the fill-in form. */
export const E_APPROVAL_SUBMISSION_ENTITY = "e_approval_submission";

/** Shared across the submissions list, the record, the request form, and workspaces. */
export const E_APPROVAL_VIEWING_AS_KEY = "toweros.e-approval.viewing-as";

/** Pages an admin can hide for the role they are viewing as. Overview matches only its own URL. */
export const E_APPROVAL_NAV_TARGETS = [
  { href: "/e-approval", label: "Overview" },
  { href: "/e-approval/forms", label: "Forms" },
  { href: "/e-approval/submissions", label: "Submissions" },
  { href: "/e-approval/approvals", label: "Approvals" },
  { href: "/e-approval/reports", label: "Reports" },
] as const;

export function eApprovalNavTargetKey(href: string): string {
  const path = eApprovalPath(href);
  return `nav:${path}`;
}

/** A hidden page stays hidden on the list, the record, and every other link to that URL. */
export function eApprovalNavTargetHidden(
  matrix: RoleAccessMatrix | null | undefined,
  href: string,
): boolean {
  const path = eApprovalPath(href);
  if (!path.startsWith("/e-approval")) return false;
  const fields = matrix?.fields?.[E_APPROVAL_SUBMISSION_ENTITY];
  if (!fields) return false;

  for (const target of E_APPROVAL_NAV_TARGETS) {
    if (fields[eApprovalNavTargetKey(target.href)] !== "hide") continue;
    if (eApprovalPathMatchesTarget(path, target.href)) return true;
  }
  return false;
}

export function eApprovalHasHiddenNavTargets(matrix: RoleAccessMatrix | null | undefined): boolean {
  const fields = matrix?.fields?.[E_APPROVAL_SUBMISSION_ENTITY];
  if (!fields) return false;
  return E_APPROVAL_NAV_TARGETS.some((target) => fields[eApprovalNavTargetKey(target.href)] === "hide");
}

function eApprovalPath(href: string): string {
  return (href.split("?")[0] ?? href).split("#")[0] ?? href;
}

function eApprovalPathMatchesTarget(path: string, target: string): boolean {
  if (target === "/e-approval") return path === "/e-approval" || path === "/e-approval/";
  if (path === target) return true;
  return path.startsWith(`${target}/`) && !path.startsWith(`${target}/new`);
}

/** The page a right-click is about. Longer paths win, so a record stays on Submissions. */
export function eApprovalNavTargetForPath(href: string): (typeof E_APPROVAL_NAV_TARGETS)[number] | null {
  const path = eApprovalPath(href);
  const ordered = [...E_APPROVAL_NAV_TARGETS].sort((left, right) => right.href.length - left.href.length);
  return ordered.find((target) => eApprovalPathMatchesTarget(path, target.href)) ?? null;
}

export const E_APPROVAL_LIST_FIELDS = [
  { id: "document_no", label: "Document" },
  { id: "form_name", label: "Form" },
  { id: "subsidiary", label: "Subsidiary" },
  { id: "department", label: "Department" },
  { id: "status", label: "Status" },
  { id: "requestor", label: "Requestor" },
  { id: "current_step", label: "Step" },
] as const;

/**
 * Missing slug stays allowed. A role configured only for Assets must not lose E-Forms.
 * Once this slug has a row, the row decides.
 */
export function eApprovalEntityAllows(
  matrix: RoleAccessMatrix | null | undefined,
  action: EntityAccessAction,
): boolean {
  const row = matrix?.entities?.[E_APPROVAL_SUBMISSION_ENTITY];
  if (!row) return true;
  return canEntityAction(matrix, E_APPROVAL_SUBMISSION_ENTITY, action);
}

/** Lines and exports use requestor_name. The matrix stores requestor. */
export function eApprovalFieldKey(columnId: string): string {
  if (columnId === "requestor_name" || columnId === "requestor_email") return "requestor";
  return columnId;
}

type NavRule = {
  match: (path: string) => boolean;
  permissions: string[];
  mode: "any" | "all";
  action?: EntityAccessAction;
};

/** Longer prefixes first so /submissions/new is not treated as the submissions list. */
const NAV_RULES: NavRule[] = [
  {
    match: (path) => path.startsWith("/e-approval/submissions/new"),
    permissions: ["e_approval:submissions:create"],
    mode: "all",
    action: "create",
  },
  {
    match: (path) => path.startsWith("/e-approval/request"),
    permissions: ["e_approval:submissions:create"],
    mode: "all",
    action: "create",
  },
  {
    match: (path) => path.startsWith("/e-approval/submissions"),
    permissions: ["e_approval:submissions:view"],
    mode: "all",
    action: "view",
  },
  {
    match: (path) => path.startsWith("/e-approval/approvals"),
    permissions: ["e_approval:approve"],
    mode: "all",
    action: "view",
  },
  {
    match: (path) => path.startsWith("/e-approval/reports"),
    permissions: ["e_approval:audit:view", "e_approval:submissions:view"],
    mode: "any",
    action: "view",
  },
  {
    match: (path) => path.startsWith("/e-approval/forms"),
    permissions: ["e_approval:forms:manage"],
    mode: "all",
  },
  {
    match: (path) =>
      path.startsWith("/e-approval/settings") ||
      path.startsWith("/e-approval/master-data") ||
      path.startsWith("/e-approval/approval-policies"),
    permissions: ["e_approval:settings:manage"],
    mode: "all",
  },
  {
    match: (path) => path.startsWith("/e-approval/audit"),
    permissions: ["e_approval:audit:view"],
    mode: "all",
  },
  {
    match: (path) => path.startsWith("/e-approval/attachments"),
    permissions: ["e_approval:submissions:view"],
    mode: "all",
    action: "view",
  },
  {
    match: (path) => path.startsWith("/e-approval/w/"),
    permissions: ["e_approval:view", "e_approval:submissions:view"],
    mode: "any",
    action: "view",
  },
  {
    match: (path) => path === "/e-approval" || path === "/e-approval/",
    permissions: ["e_approval:view"],
    mode: "all",
    action: "view",
  },
  {
    match: (path) => path.startsWith("/e-approval/profile") || path.startsWith("/e-approval/notifications"),
    permissions: ["e_approval:view"],
    mode: "all",
  },
];

function roleHasPermissions(granted: readonly string[], required: string[], mode: "any" | "all"): boolean {
  if (required.length === 0) return true;
  return mode === "any"
    ? required.some((permission) => granted.includes(permission))
    : required.every((permission) => granted.includes(permission));
}

/**
 * Whether a preview role may open this href. Paths outside E-Forms stay visible.
 * Callers skip this for the workspace owner, who stays unrestricted.
 */
export function eApprovalPreviewAllowsPath(
  granted: readonly string[] | null | undefined,
  matrix: RoleAccessMatrix | null | undefined,
  href: string,
): boolean {
  const path = eApprovalPath(href);
  if (!path.startsWith("/e-approval")) return true;
  if (eApprovalNavTargetHidden(matrix, path)) return false;

  const permissions = granted ?? [];
  const rule = NAV_RULES.find((candidate) => candidate.match(path)) ?? {
    permissions: ["e_approval:view"],
    mode: "all" as const,
    action: "view" as const,
  };

  if (!roleHasPermissions(permissions, rule.permissions, rule.mode)) return false;
  if (rule.action && !eApprovalEntityAllows(matrix, rule.action)) return false;
  return true;
}

type PreviewNavNode = {
  title: string;
  href?: string | null;
  items?: Array<{ href?: string | null }>;
};

/** Hide E-Forms links the preview role cannot open. The E-Forms group stays so the section can still be right-clicked. */
export function filterNavGroupsForEApprovalPreview<T extends PreviewNavNode>(
  groups: Array<{ group: string; items: T[] }>,
  allowsHref: (href: string) => boolean,
): Array<{ group: string; items: T[] }> {
  return groups
    .map((group) => ({
      ...group,
      items: group.items.flatMap((item): T[] => {
        if (item.items) {
          const next = item.items.filter((sub) => allowsHref(sub.href ?? ""));
          if (item.title === "E-Forms") {
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
