import type { FieldAccessLevel, RoleAccessMatrix } from "@/lib/api/modules/admin-roles-api";
import { getErrorMessage } from "@/lib/api/error";
import { useNotificationStore } from "@/stores/notification-store";

export type EntityAccessAction = "view" | "view_own" | "create" | "edit" | "delete" | "export";

/** Nav hrefs that are not a single entity table (hidden when entity ACL is restricted). */
const DYN_NON_ENTITY_SEGMENTS = new Set([
  "fields",
  "field-groups",
  "reports",
  "executive-dashboard",
  "ticketing-board",
]);

/** Map special Dynamic Entities routes onto an entity slug for ACL checks. */
const DYN_HREF_ENTITY_ALIASES: Record<string, string> = {
  "ticketing-board": "site_tickets",
};

const ACTION_LABELS: Record<EntityAccessAction | "clone" | "manage_fields", string> = {
  view: "view",
  view_own: "view",
  create: "create",
  edit: "edit",
  delete: "delete",
  export: "export",
  clone: "duplicate",
  manage_fields: "manage fields for",
};

/**
 * True when the merged role access matrix has at least one entity ACL row.
 * When false, sidebar/actions fall back to Spatie `dynamic_entities:*` permissions only.
 */
export function hasEntityAccessRules(matrix: RoleAccessMatrix | null | undefined): boolean {
  const entities = matrix?.entities;
  return !!entities && Object.keys(entities).length > 0;
}

export function canViewEntityInMatrix(
  matrix: RoleAccessMatrix | null | undefined,
  slug: string,
): boolean {
  return canEntityAction(matrix, slug, "view");
}

export function canEntityAction(
  matrix: RoleAccessMatrix | null | undefined,
  slug: string,
  action: EntityAccessAction,
): boolean {
  if (!hasEntityAccessRules(matrix)) {
    return true;
  }
  const row = matrix?.entities?.[slug];
  if (!row) {
    return false;
  }
  if (action === "view" || action === "view_own") {
    return Boolean(row.view || row.view_own);
  }
  return Boolean(row[action]);
}

/**
 * Workflow button ACL. When no workflow rows exist in the matrix, allow
 * (Spatie `dynamic_entities:records:manage` still gates the API).
 */
export function canWorkflowAction(
  matrix: RoleAccessMatrix | null | undefined,
  slug: string,
  action: string,
): boolean {
  const workflows = matrix?.workflows;
  if (!workflows || Object.keys(workflows).length === 0) {
    return true;
  }
  return Boolean(workflows[slug]?.[action]);
}

export function fieldAccessLevel(
  matrix: RoleAccessMatrix | null | undefined,
  slug: string,
  field: string,
): FieldAccessLevel {
  const level = matrix?.fields?.[slug]?.[field];
  return level ?? "full";
}

export function isFieldHiddenForRole(
  matrix: RoleAccessMatrix | null | undefined,
  slug: string,
  field: string,
): boolean {
  return fieldAccessLevel(matrix, slug, field) === "hide";
}

export function isFieldReadOnlyForRole(
  matrix: RoleAccessMatrix | null | undefined,
  slug: string,
  field: string,
): boolean {
  const level = fieldAccessLevel(matrix, slug, field);
  return level === "view" || level === "table_record" || level === "record" || level === "form";
}

/**
 * Resolve which Dynamic Entity slug (if any) a workspace href represents.
 * Returns `null` for catalog/reports/admin routes that should hide under restricted ACL.
 * Returns `undefined` for non-Dynamic-Entities hrefs (no entity filter).
 */
export function dynEntitySlugForHref(href: string): string | null | undefined {
  const path = href.split("?")[0] ?? href;
  if (!path.startsWith("/dynamic-entities")) {
    return undefined;
  }
  if (path === "/dynamic-entities" || path === "/dynamic-entities/") {
    return null;
  }

  const match = path.match(/^\/dynamic-entities\/([^/]+)/);
  if (!match) {
    return null;
  }

  const segment = match[1];
  if (segment.startsWith("reports") || DYN_NON_ENTITY_SEGMENTS.has(segment)) {
    if (DYN_HREF_ENTITY_ALIASES[segment]) {
      return DYN_HREF_ENTITY_ALIASES[segment];
    }
    if (segment === "fields" || segment === "field-groups") {
      return undefined;
    }
    return null;
  }

  return segment;
}

/** Whether a nav href should remain visible given the user's merged access matrix. */
export function canAccessDynNavHref(
  href: string | undefined,
  matrix: RoleAccessMatrix | null | undefined,
): boolean {
  if (!href || !hasEntityAccessRules(matrix)) {
    return true;
  }
  const slug = dynEntitySlugForHref(href);
  if (slug === undefined) {
    return true;
  }
  if (slug === null) {
    return false;
  }
  return canViewEntityInMatrix(matrix, slug);
}

export function dynPermissionDeniedMessage(
  action: EntityAccessAction | "clone" | "manage_fields",
  entityLabel?: string,
): string {
  const verb = ACTION_LABELS[action] ?? action;
  const target = entityLabel?.trim() || "this record";
  return `Your role does not allow you to ${verb} ${target}. Ask an administrator to update Data Access for your role.`;
}

/** Toast a permission denial; prefer API message when it is already user-friendly. */
export function notifyDynPermissionDenied(options: {
  action: EntityAccessAction | "clone" | "manage_fields";
  entityLabel?: string;
  error?: unknown;
}): void {
  const fallback = dynPermissionDeniedMessage(options.action, options.entityLabel);
  let message = fallback;
  if (options.error !== undefined) {
    const apiMessage = getErrorMessage(options.error).trim();
    if (
      apiMessage &&
      !/^forbidden\.?$/i.test(apiMessage) &&
      !/^this action is unauthorized\.?$/i.test(apiMessage) &&
      !/^unable to /i.test(apiMessage)
    ) {
      message = apiMessage;
    }
  }

  useNotificationStore.getState().push({
    level: "warning",
    title: "Access denied",
    message,
  });
}
