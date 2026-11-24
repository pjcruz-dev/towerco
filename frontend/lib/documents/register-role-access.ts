import type { RoleAccessMatrix } from "@/lib/api/modules/admin-roles-api";
import { canEntityAction, type EntityAccessAction } from "@/lib/rbac/entity-access";

/** Access-matrix slug for the controlled document register. */
export const CONTROLLED_DOCUMENT_ENTITY = "controlled_document";

/** Shared by the register pages and the Document register navigation. */
export const DOCUMENT_REGISTER_VIEWING_AS_KEY = "toweros.document-register.viewing-as";

/** Pages an admin can hide for the role they are viewing as. Overview matches only its own URL. */
export const DOCUMENT_REGISTER_NAV_TARGETS = [
  { href: "/documents/controlled", label: "Overview" },
  { href: "/documents/controlled/register", label: "Register" },
  { href: "/documents/controlled/approvals", label: "Approvals" },
  { href: "/documents/controlled/obsolete", label: "Files" },
  { href: "/documents/controlled/reports", label: "Reports" },
] as const;

export function documentRegisterNavTargetKey(href: string): string {
  return `nav:${documentRegisterPath(href)}`;
}

/** A hidden page stays hidden in navigation and on that URL. */
export function documentRegisterNavTargetHidden(
  matrix: RoleAccessMatrix | null | undefined,
  href: string,
): boolean {
  const path = documentRegisterPath(href);
  if (!path.startsWith("/documents")) return false;
  const fields = matrix?.fields?.[CONTROLLED_DOCUMENT_ENTITY];
  if (!fields) return false;

  for (const target of DOCUMENT_REGISTER_NAV_TARGETS) {
    if (fields[documentRegisterNavTargetKey(target.href)] !== "hide") continue;
    if (documentRegisterPathMatchesTarget(path, target.href)) return true;
  }
  return false;
}

export function documentRegisterHasHiddenNavTargets(matrix: RoleAccessMatrix | null | undefined): boolean {
  const fields = matrix?.fields?.[CONTROLLED_DOCUMENT_ENTITY];
  if (!fields) return false;
  return DOCUMENT_REGISTER_NAV_TARGETS.some(
    (target) => fields[documentRegisterNavTargetKey(target.href)] === "hide",
  );
}

function documentRegisterPath(href: string): string {
  return (href.split("?")[0] ?? href).split("#")[0] ?? href;
}

function documentRegisterPathMatchesTarget(path: string, target: string): boolean {
  if (target === "/documents/controlled") {
    return path === "/documents/controlled" || path === "/documents/controlled/";
  }
  if (target === "/documents/controlled/register") {
    return (
      path === target ||
      path.startsWith(`${target}/`) ||
      path.startsWith("/documents/controlled/published") ||
      path === "/documents/controlled/new" ||
      path.startsWith("/documents/controlled/new/")
    );
  }
  if (target === "/documents/controlled/approvals") {
    return (
      path === target ||
      path.startsWith(`${target}/`) ||
      path.startsWith("/documents/controlled/requests")
    );
  }
  return path === target || path.startsWith(`${target}/`);
}

/** The page a right-click is about. Longer paths win, so a file stays on Register. */
export function documentRegisterNavTargetForPath(
  href: string,
): (typeof DOCUMENT_REGISTER_NAV_TARGETS)[number] | null {
  const path = documentRegisterPath(href);
  const ordered = [...DOCUMENT_REGISTER_NAV_TARGETS].sort((left, right) => right.href.length - left.href.length);
  return ordered.find((target) => documentRegisterPathMatchesTarget(path, target.href)) ?? null;
}

export const CONTROLLED_DOCUMENT_LIST_FIELDS = [
  { id: "document_code", label: "Code" },
  { id: "dcf_number", label: "DCF number" },
  { id: "title", label: "Title" },
  { id: "document_type", label: "Type" },
  { id: "department", label: "Department" },
  { id: "current_revision", label: "Revision" },
  { id: "status", label: "Status" },
  { id: "effective_date", label: "Effective" },
  { id: "next_review_date", label: "Next review" },
  { id: "published_at", label: "Published" },
  { id: "aging", label: "Aging" },
  { id: "has_file", label: "File" },
  { id: "created_by_name", label: "Created by" },
] as const;

/**
 * Missing slug stays allowed. A role configured only for Assets or E-Forms must not lose the register.
 * Once this slug has a row, the row decides.
 */
export function controlledDocumentAllows(
  matrix: RoleAccessMatrix | null | undefined,
  action: EntityAccessAction,
): boolean {
  const row = matrix?.entities?.[CONTROLLED_DOCUMENT_ENTITY];
  if (!row) return true;
  return canEntityAction(matrix, CONTROLLED_DOCUMENT_ENTITY, action);
}

type NavRule = {
  match: (path: string) => boolean;
  permissions: string[];
  mode: "any" | "all";
  action?: EntityAccessAction;
};

const NAV_RULES: NavRule[] = [
  {
    match: (path) => path.startsWith("/documents/controlled/access") || path.startsWith("/documents/settings"),
    permissions: ["documents:controlled:manage"],
    mode: "all",
  },
  {
    match: (path) => path.startsWith("/documents/controlled/reports"),
    permissions: ["documents:controlled:view"],
    mode: "all",
    action: "view",
  },
  {
    match: (path) => path.startsWith("/documents"),
    permissions: ["documents:controlled:view"],
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

/** Whether a preview role may open this href. Paths outside Document Register stay visible. */
export function documentRegisterPreviewAllowsPath(
  granted: readonly string[] | null | undefined,
  matrix: RoleAccessMatrix | null | undefined,
  href: string,
): boolean {
  const path = documentRegisterPath(href);
  if (!path.startsWith("/documents")) return true;
  if (documentRegisterNavTargetHidden(matrix, path)) return false;

  const permissions = granted ?? [];
  const rule = NAV_RULES.find((candidate) => candidate.match(path));
  if (!rule) return true;
  if (!roleHasPermissions(permissions, rule.permissions, rule.mode)) return false;
  if (rule.action && !controlledDocumentAllows(matrix, rule.action)) return false;
  return true;
}

/** What the navigation does with this page: shown, hidden by the admin, or unavailable for the role. */
export function documentRegisterPageListState(
  granted: readonly string[] | null | undefined,
  matrix: RoleAccessMatrix | null | undefined,
  href: string,
): "shown" | "hidden" | "no-access" {
  const fields = { ...(matrix?.fields?.[CONTROLLED_DOCUMENT_ENTITY] ?? {}) };
  const key = documentRegisterNavTargetKey(href);
  const hidden = fields[key] === "hide";
  delete fields[key];
  const stripped: RoleAccessMatrix = {
    ...(matrix ?? {}),
    fields: {
      ...(matrix?.fields ?? {}),
      [CONTROLLED_DOCUMENT_ENTITY]: fields,
    },
  };
  if (!documentRegisterPreviewAllowsPath(granted, stripped, href)) return "no-access";
  return hidden ? "hidden" : "shown";
}

type PreviewNavNode = {
  title: string;
  href?: string | null;
  items?: Array<{ href?: string | null }>;
};

/** Hide Document Register links the preview role cannot open. The group stays so it can still be right-clicked. */
export function filterNavGroupsForDocumentRegisterPreview<T extends PreviewNavNode>(
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
          if (
            item.title === "EDCS" ||
            item.title === "Electronic Document Control System" ||
            item.title === "Document register"
          ) {
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
