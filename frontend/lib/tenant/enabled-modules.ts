import type { AuthUser } from "@/types/auth";

export const TENANT_MODULE_LABELS: Record<string, string> = {
  core: "Dashboard",
  team_access: "Team & Access",
  e_approval: "E-Approval",
  dynamic_entities: "Dynamic Entities",
  ticketing: "Ticketing",
  ai_assistant: "AI Assistant",
};

export const TENANT_MODULE_DESCRIPTIONS: Record<string, string> = {
  ai_assistant:
    "In-app help assistant for workflows, permissions, and how-to guidance. Opt-in — not included with Dynamic Entities alone.",
  dynamic_entities:
    "Dynamic entity packs (PM, Procurement, Finance, Ticketing) with Manage Fields.",
  e_approval: "Forms, submissions, and approval workflows.",
  ticketing: "Service desk tickets and queues.",
};

/** Optional modules superadmins can enable per tenant (aligned with backend TOGGLEABLE_MODULES). */
export const TOGGLEABLE_WORKSPACE_MODULES = [
  "e_approval",
  "ticketing",
  "dynamic_entities",
  "ai_assistant",
] as const;

type WorkspaceModulesCatalog = {
  toggleable_modules?: string[];
  platform_modules?: string[];
};

/** Merge API catalog with the frontend module list so new modules appear once deployed. */
export function resolveToggleableWorkspaceModules(
  catalog: WorkspaceModulesCatalog | undefined,
): string[] {
  if (!catalog) {
    return [...TOGGLEABLE_WORKSPACE_MODULES];
  }

  const platform = new Set(catalog.platform_modules ?? []);
  const merged = new Set(catalog.toggleable_modules ?? []);

  for (const moduleKey of TOGGLEABLE_WORKSPACE_MODULES) {
    if (platform.has(moduleKey)) {
      merged.add(moduleKey);
    }
  }

  return TOGGLEABLE_WORKSPACE_MODULES.filter((moduleKey) => merged.has(moduleKey));
}

/** Toggleable workspace modules shown as badges on the platform tenant directory. */
export const PLATFORM_TENANT_MODULE_BADGE_ORDER = [
  "e_approval",
  "ticketing",
  "dynamic_entities",
  "ai_assistant",
] as const;

export function resolveEnabledModulesForUser(
  user: AuthUser | null | undefined,
  activeTenantId: string | null,
): string[] {
  if (!user) {
    return [];
  }

  const access = user.tenantAccesses.find((item) => item.tenantId === activeTenantId);
  if (access?.enabledModules && access.enabledModules.length > 0) {
    return access.enabledModules;
  }

  if (user.enabledModules && user.enabledModules.length > 0) {
    return user.enabledModules;
  }

  return [];
}

export function isTenantModuleEnabled(
  enabledModules: string[],
  module: string | undefined,
): boolean {
  if (!module) {
    return true;
  }

  return enabledModules.includes(module);
}

export function notificationsModuleEnabled(enabledModules: string[]): boolean {
  return (
    enabledModules.includes("e_approval")
    || enabledModules.includes("ticketing")
    || enabledModules.includes("dynamic_entities")
  );
}
