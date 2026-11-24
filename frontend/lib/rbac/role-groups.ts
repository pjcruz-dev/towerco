import type { AdminRoleRow } from "@/lib/api/modules/admin-roles-api";
import { roleDisplayLabel } from "@/lib/rbac/role-display-labels";

export type RoleGroup = {
  id: string;
  label: string;
  roles: AdminRoleRow[];
};

export type GroupRolesOptions = {
  /** Tenant effective modules from `/admin/roles` (`enabled_modules`). */
  enabledModules?: string[] | null;
  /** Always keep these role names visible (e.g. already assigned on the user). */
  alwaysIncludeRoleNames?: string[];
  /** When set, the Viewing as menu lists only this module's roles. */
  moduleId?: string | null;
};

const CORE_ROLE_NAMES = new Set([
  "tenant_admin",
  "administrator",
  "billing",
  "viewer",
  "manager",
  "finance",
  "admin",
  "commercial_sales_officer",
  "finance_officer",
  "procurement_officer",
  "project_manager",
  "sa_officer",
  "sales",
  "staff",
]);

/** Leftover owner aliases. Hidden from pickers; assignments fold onto tenant_admin. */
export const HIDDEN_TENANT_ROLE_ALIASES = new Set(["administrator"]);

/** Metacoresoft Role Management display order. */
export const ATC_OPERATIONAL_ROLE_ORDER: string[] = [
  "admin",
  "commercial_sales_officer",
  "finance_officer",
  "procurement_officer",
  "project_manager",
  "sa_officer",
  "sales",
  "staff",
];

/**
 * Module role groups. `id` must match tenant `enabled_modules` keys.
 */
const MODULE_GROUP_ORDER: { id: string; label: string; prefix: string }[] = [
  { id: "ticketing", label: "Ticketing", prefix: "ticketing_" },
  { id: "asset_management", label: "Assets", prefix: "assets_" },
  { id: "documents", label: "Documents", prefix: "documents_" },
  { id: "document_register", label: "Document register", prefix: "dcf_" },
  { id: "e_approval", label: "E-Forms", prefix: "e_approval_" },
  { id: "ai_assistant", label: "AI Assistant", prefix: "ai_assistant_" },
  { id: "doc_extract", label: "DocExtract", prefix: "doc_extract_" },
  { id: "dynamic_entities", label: "Dynamic Entities", prefix: "dynamic_entities_" },
];

const TIER_ORDER = [
  "viewer",
  "user",
  "employee",
  "contributor",
  "requestor",
  "author",
  "operator",
  "approver",
  "controller",
  "admin",
];

function tierSortKey(roleName: string): number {
  for (let i = 0; i < TIER_ORDER.length; i++) {
    if (roleName.endsWith(`_${TIER_ORDER[i]}`) || roleName === TIER_ORDER[i]) {
      return i;
    }
  }

  return TIER_ORDER.length;
}

function sortRoles(roles: AdminRoleRow[]): AdminRoleRow[] {
  return [...roles].sort((a, b) => tierSortKey(a.name) - tierSortKey(b.name) || a.name.localeCompare(b.name));
}

/** Resolve which tenant module a system role belongs to (null = core/custom). */
export function moduleIdForRoleName(roleName: string): string | null {
  if (CORE_ROLE_NAMES.has(roleName)) {
    return null;
  }
  for (const moduleGroup of MODULE_GROUP_ORDER) {
    if (roleName.startsWith(moduleGroup.prefix)) {
      return moduleGroup.id;
    }
  }
  return null;
}

export function roleBelongsToEnabledModules(
  roleName: string,
  enabledModules: string[] | null | undefined,
): boolean {
  if (!enabledModules || enabledModules.length === 0) {
    return true;
  }
  const moduleId = moduleIdForRoleName(roleName);
  if (moduleId === null) {
    return true;
  }
  return enabledModules.includes(moduleId);
}

export function filterRolesForEnabledModules(
  roles: AdminRoleRow[],
  options?: GroupRolesOptions,
): AdminRoleRow[] {
  const enabled = options?.enabledModules;
  const alwaysInclude = new Set(options?.alwaysIncludeRoleNames ?? []);

  return roles.filter((role) => {
    if (HIDDEN_TENANT_ROLE_ALIASES.has(role.name) && !alwaysInclude.has(role.name)) {
      return false;
    }

    return alwaysInclude.has(role.name) || roleBelongsToEnabledModules(role.name, enabled);
  });
}

export function groupRolesByType(roles: AdminRoleRow[], options?: GroupRolesOptions): RoleGroup[] {
  const visibleRoles = filterRolesForEnabledModules(roles, options);
  const assigned = new Set<string>();
  const groups: RoleGroup[] = [];
  const enabled = options?.enabledModules;
  const moduleEnabled = (moduleId: string) =>
    !enabled || enabled.length === 0 || enabled.includes(moduleId);

  const baseline = sortRoles(visibleRoles.filter((role) => role.is_baseline));
  baseline.forEach((role) => assigned.add(role.name));
  if (baseline.length > 0) {
    groups.push({ id: "baseline", label: "Core roles", roles: baseline });
  }

  for (const moduleGroup of MODULE_GROUP_ORDER) {
    if (!moduleEnabled(moduleGroup.id)) {
      // Still show roles that must remain visible (already assigned).
      const forced = sortRoles(
        visibleRoles.filter(
          (role) =>
            role.name.startsWith(moduleGroup.prefix) &&
            !assigned.has(role.name) &&
            (options?.alwaysIncludeRoleNames ?? []).includes(role.name),
        ),
      );
      if (forced.length > 0) {
        forced.forEach((role) => assigned.add(role.name));
        groups.push({
          id: moduleGroup.id,
          label: `${moduleGroup.label} (assigned · module off)`,
          roles: forced,
        });
      }
      continue;
    }

    const moduleRoles = sortRoles(
      visibleRoles.filter((role) => role.name.startsWith(moduleGroup.prefix) && !assigned.has(role.name)),
    );
    moduleRoles.forEach((role) => assigned.add(role.name));
    if (moduleRoles.length > 0) {
      groups.push({ id: moduleGroup.id, label: moduleGroup.label, roles: moduleRoles });
    }
  }

  const custom = sortRoles(
    visibleRoles.filter(
      (role) =>
        !assigned.has(role.name) && !role.is_baseline && !CORE_ROLE_NAMES.has(role.name) && !role.is_system,
    ),
  );

  if (custom.length > 0) {
    groups.push({ id: "custom", label: "Custom roles", roles: custom });
  }

  return groups;
}

/**
 * Every role, for the Viewing as menu.
 * Workspace holds owner and cross-module roles. Module groups follow. Custom roles are last.
 */
export function groupRolesForMenu(roles: AdminRoleRow[], options?: GroupRolesOptions): RoleGroup[] {
  const groups = groupRolesByType(roles, options);
  const placed = new Set(groups.flatMap((group) => group.roles.map((role) => role.name)));
  const workspaceExtras = sortRoles(
    filterRolesForEnabledModules(roles, options).filter((role) => !placed.has(role.name)),
  );
  const baseline = groups.find((group) => group.id === "baseline");
  const modulesAndCustom = groups.filter((group) => group.id !== "baseline");
  const workspaceRoles = sortByLabel([...(baseline?.roles ?? []), ...workspaceExtras]);

  const menu = [
    ...(workspaceRoles.length > 0
      ? [{ id: "workspace", label: "Workspace", roles: workspaceRoles }]
      : []),
    ...modulesAndCustom,
  ];
  if (!options?.moduleId) return menu;
  return menu.filter((group) => group.id === options.moduleId);
}

/** Short label once the group heading already names the module. */
export function roleLabelWithinGroup(groupId: string, roleName: string): string {
  const full = roleDisplayLabel(roleName);
  const prefix = MODULE_GROUP_ORDER.find((group) => group.id === groupId)?.prefix;
  if (!prefix) return full;

  const head = prefix.replace(/_/g, " ");
  if (!full.toLowerCase().startsWith(head)) return full;
  const rest = full.slice(head.length).trim();
  if (!rest) return full;
  return rest.charAt(0).toUpperCase() + rest.slice(1);
}

function sortByLabel(roles: AdminRoleRow[]): AdminRoleRow[] {
  return [...roles].sort((a, b) =>
    roleDisplayLabel(a.name).localeCompare(roleDisplayLabel(b.name), undefined, { sensitivity: "base" }),
  );
}
