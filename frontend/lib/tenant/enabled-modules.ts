import type { AuthUser } from "@/types/auth";

export const TENANT_MODULE_LABELS: Record<string, string> = {
  core: "Dashboard",
  team_access: "Team & Access",
  project_one: "Project-One",
  e_approval: "E-Approval",
  gis: "GIS",
  sites: "Sites",
  tower_one: "Tower-One",
  fiber_one: "Fiber-One",
  asset_one: "Asset-One",
  ticketing: "Ticketing",
  procurement_one: "Procurement-One",
  finance_one: "Finance-One",
  billings: "Billings",
  documents: "Documents",
  document_register: "Document register",
  ai_assistant: "AI Assistant",
};

export const TENANT_MODULE_DESCRIPTIONS: Record<string, string> = {
  billings: "Tenant subscription, usage, and self-serve plan billing (/billing).",
  documents: "Expiring leases, permits, and contracts across sites.",
  document_register:
    "ISO master list of approved documents; start requests and revisions via E-Approval.",
  ai_assistant:
    "In-app help assistant for workflows, permissions, and how-to guidance.",
};

/** Optional modules superadmins can enable per tenant (must stay aligned with backend TOGGLEABLE_MODULES). */
export const TOGGLEABLE_WORKSPACE_MODULES = [
  "project_one",
  "e_approval",
  "ticketing",
  "procurement_one",
  "finance_one",
  "billings",
  "sites",
  "documents",
  "document_register",
  "gis",
  "tower_one",
  "fiber_one",
  "asset_one",
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
  "project_one",
  "ticketing",
  "procurement_one",
  "finance_one",
  "billings",
  "documents",
  "document_register",
  "sites",
  "gis",
  "tower_one",
  "fiber_one",
  "asset_one",
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
    || enabledModules.includes("project_one")
    || enabledModules.includes("ticketing")
    || enabledModules.includes("procurement_one")
    || enabledModules.includes("finance_one")
  );
}
