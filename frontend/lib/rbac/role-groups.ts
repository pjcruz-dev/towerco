import type { AdminRoleRow } from "@/lib/api/modules/admin-roles-api";

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
};

const CORE_ROLE_NAMES = new Set(["tenant_admin", "billing", "viewer", "manager", "finance"]);

/**
 * Module role groups. `id` must match tenant `enabled_modules` keys.
 */
const MODULE_GROUP_ORDER: { id: string; label: string; prefix: string }[] = [
  { id: "project_one", label: "Project-One", prefix: "project_one_" },
  { id: "ticketing", label: "Ticketing", prefix: "ticketing_" },
  { id: "procurement_one", label: "Procurement-One", prefix: "procurement_" },
  { id: "finance_one", label: "Finance-One", prefix: "finance_" },
  { id: "documents", label: "Documents", prefix: "documents_" },
  { id: "document_register", label: "Document register", prefix: "dcf_" },
  { id: "sites", label: "Sites", prefix: "sites_" },
  { id: "e_approval", label: "E-Approval", prefix: "e_approval_" },
  { id: "ai_assistant", label: "AI Assistant", prefix: "ai_assistant_" },
];

const DISCIPLINE_ROLES = new Set(["saq_approver", "pmo_approver", "cme_approver"]);

const TIER_ORDER = ["viewer", "contributor", "requestor", "author", "operator", "approver", "controller", "admin"];

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
  if (CORE_ROLE_NAMES.has(roleName) && roleName !== "finance") {
    return null;
  }
  if (roleName === "finance") {
    // Legacy cross-module finance role — treat as Project-One gated.
    return "project_one";
  }
  if (DISCIPLINE_ROLES.has(roleName)) {
    return "project_one";
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

  return roles.filter(
    (role) => alwaysInclude.has(role.name) || roleBelongsToEnabledModules(role.name, enabled),
  );
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

  if (moduleEnabled("project_one")) {
    const discipline = sortRoles(visibleRoles.filter((role) => DISCIPLINE_ROLES.has(role.name)));
    discipline.forEach((role) => assigned.add(role.name));
    if (discipline.length > 0) {
      groups.push({ id: "discipline", label: "Project-One discipline add-ons", roles: discipline });
    }
  } else {
    const forcedDiscipline = sortRoles(
      visibleRoles.filter(
        (role) =>
          DISCIPLINE_ROLES.has(role.name) &&
          (options?.alwaysIncludeRoleNames ?? []).includes(role.name),
      ),
    );
    if (forcedDiscipline.length > 0) {
      forcedDiscipline.forEach((role) => assigned.add(role.name));
      groups.push({
        id: "discipline",
        label: "Project-One discipline add-ons (assigned · module off)",
        roles: forcedDiscipline,
      });
    }
  }

  const financeLegacy = visibleRoles.filter((role) => role.name === "finance" && !assigned.has(role.name));
  financeLegacy.forEach((role) => assigned.add(role.name));
  if (financeLegacy.length > 0 && (moduleEnabled("project_one") || moduleEnabled("finance_one"))) {
    groups.push({ id: "finance_legacy", label: "Project finance (legacy)", roles: financeLegacy });
  } else if (financeLegacy.length > 0) {
    const forcedFinance = financeLegacy.filter((role) =>
      (options?.alwaysIncludeRoleNames ?? []).includes(role.name),
    );
    if (forcedFinance.length > 0) {
      groups.push({
        id: "finance_legacy",
        label: "Project finance (legacy · assigned · module off)",
        roles: forcedFinance,
      });
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
